<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Caja;
use App\Models\CapitalCuenta;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Configuracion;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\MovimientoCaja;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\ProveedorCuentaPorPagar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;

class CompraController extends Controller
{
    public function index(): JsonResponse
    {
        $compras = Compra::query()
            ->with(['proveedor:id,nombre'])
            ->withCount('detalles')
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->get([
                'id',
                'numero',
                'proveedor_id',
                'fecha_compra',
                'estado',
                'metodo_pago',
                'caja_id',
                'tipo_compra',
                'es_consignacion',
                'total',
                'tipo_documento',
                'numero_documento',
                'factura_receptor',
                'nit_receptor',
                'nit_proveedor',
                'serie_documento',
                'created_at',
            ]);

        return response()->json([
            'data' => $compras,
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $categorias = Categoria::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $proveedores = Proveedor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit']);

        $proveedorGeneral = Proveedor::query()
            ->where('activo', true)
            ->where('nombre', 'Proveedores varios')
            ->value('id');

        $productos = Producto::query()
            ->where('activo', true)
            ->with(['unidadMedida:id,nombre,abreviatura'])
            ->orderBy('nombre')
            ->get([
                'id',
                'categoria_id',
                'nombre',
                'codigo_barra',
                'palabras_clave',
                'costo_promedio',
                'precio_venta',
                'stock_actual',
                'unidad_medida_id',
                'control_vencimiento',
            ]);

        $puedeUsarCajaPos = $user?->role?->code === 'admin'
            || $user?->hasPermission('ventas');
        $cajaPosAbierta = $puedeUsarCajaPos
            ? Caja::query()
                ->where('usuario_id', (int) $user?->id)
                ->where('estado', 'abierta')
                ->latest('id')
                ->first(['id', 'fecha_apertura'])
            : null;
        $metodosPago = [
            ['value' => 'caja_general', 'label' => 'Caja general'],
            ['value' => 'banco', 'label' => 'Banco'],
        ];

        if ($cajaPosAbierta) {
            $metodosPago[] = ['value' => 'caja_pos', 'label' => 'Efectivo de mi caja POS abierta'];
        }

        return response()->json([
            'data' => [
                'categorias'          => $categorias,
                'proveedores'         => $proveedores,
                'proveedor_general_id' => $proveedorGeneral,
                'porcentaje_utilidad_compra' => (int) Configuracion::valor('porcentaje_utilidad_compra', 25),
                'consignacion_habilitada' => (bool) Configuracion::valor('habilitar_consignacion', false),
                'metodos_pago' => $metodosPago,
                'caja_pos_abierta' => $cajaPosAbierta,
                'capital_cuentas' => CapitalCuenta::query()
                    ->whereIn('codigo', ['caja_general', 'banco'])
                    ->where('activo', true)
                    ->orderBy('id')
                    ->get(['id', 'codigo', 'nombre', 'saldo_actual']),
                'nit_empresa' => Configuracion::valor('nit_empresa', ''),
                'productos'           => $productos,
            ],
        ]);
    }

    public function show(Compra $compra): JsonResponse
    {
        $compra->load([
            'proveedor:id,nombre',
            'detalles:id,compra_id,producto_id,cantidad,unidad_medida,costo_unitario,subtotal,precio_venta_sugerido,precio_venta_aplicado,fecha_caducidad',
            'detalles.producto:id,nombre,codigo_barra',
        ]);

        return response()->json([
            'data' => $compra,
        ]);
    }

    public function exportarComprasFiscalesExcel(Request $request)
    {
        $compras = $this->comprasParaExportar($request);

        return Excel::download(new class($compras) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private readonly Collection $compras) {}
            public function collection(): Collection { return $this->compras; }
            public function headings(): array { return ['Fecha', 'Compra', 'Proveedor', 'Tipo documento', 'Receptor factura', 'NIT proveedor', 'Serie/DTE', 'Numero documento', 'NIT receptor', 'Total', 'Estado']; }
            public function map($compra): array {
                return [
                    optional($compra->fecha_compra)->format('d/m/Y'), $compra->numero, $compra->proveedor?->nombre,
                    $this->tipoDocumentoEtiqueta($compra->tipo_documento), $this->receptorFacturaEtiqueta($compra->factura_receptor),
                    $compra->nit_proveedor, $compra->serie_documento, $compra->numero_documento, $compra->nit_receptor,
                    (float) $compra->total, $compra->estado,
                ];
            }
            private function tipoDocumentoEtiqueta(?string $tipo): string { return match ($tipo) { 'recibo' => 'Recibo', 'factura' => 'Factura', default => 'Sin documento' }; }
            private function receptorFacturaEtiqueta(?string $receptor): string { return match ($receptor) { 'nit_empresa' => 'Mi NIT', 'cf' => 'CF', default => '-' }; }
        }, 'compras-'.now()->format('Ymd').'.xlsx');
    }

    public function exportarComprasFiscalesPdf(Request $request)
    {
        $compras = $this->comprasParaExportar($request);

        return Pdf::loadView('reportes.compras-fiscales', [
            'compras' => $compras,
            'empresa' => Configuracion::valor('nombre_empresa', config('app.name')),
            'nitEmpresa' => Configuracion::valor('nit_empresa', ''),
            'filtros' => $this->etiquetasFiltrosExportacion($request),
        ])->setPaper('letter', 'landscape')->stream('compras-'.now()->format('Ymd').'.pdf');
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizarMontos($request);
        $this->normalizarDatosFiscales($request);

        $validated = $request->validate(
            [
                'proveedor_id' => ['required', Rule::exists('proveedores', 'id')],
                'fecha_compra' => ['required', 'date'],
                'metodo_pago' => ['nullable', Rule::in(['caja_general', 'banco', 'caja_pos'])],
                'tipo_compra' => ['nullable', Rule::in(['contado', 'credito_proveedor', 'consignacion'])],
                'es_consignacion' => ['sometimes', 'boolean'],
                'tipo_documento' => ['nullable', Rule::in(['sin_documento', 'recibo', 'factura'])],
                'numero_documento' => ['nullable', 'string', 'max:100'],
                'factura_receptor' => ['nullable', Rule::in(['cf', 'nit_empresa'])],
                'nit_proveedor' => ['nullable', 'string', 'max:30'],
                'serie_documento' => ['nullable', 'string', 'max:50'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.producto_id' => ['required', Rule::exists('productos', 'id'), 'distinct'],
                'items.*.cantidad' => ['required', 'integer', 'min:1'],
                'items.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
                'items.*.precio_venta' => ['required', 'numeric', 'gt:0'],
                'items.*.fecha_caducidad' => ['nullable', 'date'],
                'items.*.nota' => ['nullable', 'string', 'max:255'],
            ],
            [
                'items.*.cantidad.integer' => 'La cantidad debe ser un numero entero.',
                'items.*.cantidad.min' => 'La cantidad debe ser mayor o igual a 1.',
                'items.*.producto_id.required' => 'Debe seleccionar un producto para cada item.',
                'items.*.producto_id.distinct' => 'No puede repetir el mismo producto en varios items de la compra.',
                'items.*.precio_venta.required' => 'El precio de venta es obligatorio para cada item.',
            ]
        );

        if (in_array(($validated['tipo_documento'] ?? null), ['recibo', 'factura'], true) && empty($validated['numero_documento'])) {
            throw ValidationException::withMessages([
                'numero_documento' => 'El numero de documento es obligatorio cuando el tipo es recibo o factura.',
            ]);
        }

        $esFacturaFiscal = ($validated['tipo_documento'] ?? null) === 'factura'
            && ($validated['factura_receptor'] ?? null) === 'nit_empresa';

        if (($validated['tipo_documento'] ?? null) === 'factura' && empty($validated['factura_receptor'])) {
            throw ValidationException::withMessages([
                'factura_receptor' => ['Indique si la factura fue emitida como CF o a su NIT.'],
            ]);
        }

        if ($esFacturaFiscal && empty($validated['nit_proveedor'])) {
            throw ValidationException::withMessages([
                'nit_proveedor' => ['El NIT del proveedor es obligatorio para una factura emitida a su NIT.'],
            ]);
        }

        $nitEmpresa = trim((string) Configuracion::valor('nit_empresa', ''));
        if ($esFacturaFiscal && $nitEmpresa === '') {
            throw ValidationException::withMessages([
                'factura_receptor' => ['Configure primero el NIT de la empresa en Configuraciones.'],
            ]);
        }

        $proveedorActivo = Proveedor::query()
            ->whereKey($validated['proveedor_id'])
            ->where('activo', true)
            ->exists();

        if (! $proveedorActivo) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor seleccionado esta inactivo y no puede usarse en compras.',
            ]);
        }

        $tipoCompra = $validated['tipo_compra'] ?? ((bool) ($validated['es_consignacion'] ?? false) ? 'consignacion' : 'contado');

        if ($tipoCompra === 'contado' && ! in_array($validated['metodo_pago'] ?? null, ['caja_general', 'banco', 'caja_pos'], true)) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'Debe seleccionar Caja general, Banco o una caja POS abierta para una compra con pago inmediato.',
            ]);
        }

        $pagaConCajaPos = $tipoCompra === 'contado' && ($validated['metodo_pago'] ?? null) === 'caja_pos';
        $puedeUsarCajaPos = $request->user()?->role?->code === 'admin'
            || $request->user()?->hasPermission('ventas');

        if ($pagaConCajaPos && ! $puedeUsarCajaPos) {
            throw ValidationException::withMessages([
                'metodo_pago' => ['Debe tener acceso a Ventas para pagar una compra desde su caja POS.'],
            ]);
        }

        if ($tipoCompra === 'consignacion' && ! (bool) Configuracion::valor('habilitar_consignacion', false)) {
            throw ValidationException::withMessages(['es_consignacion' => ['La consignación está deshabilitada para esta tienda.']]);
        }

        $porcentajeUtilidadCompra = max(0, (int) Configuracion::valor('porcentaje_utilidad_compra', 25));
        $userId = (int) $request->user()->id;

        $result = DB::transaction(function () use ($validated, $porcentajeUtilidadCompra, $userId, $tipoCompra, $esFacturaFiscal, $nitEmpresa, $pagaConCajaPos) {
            $esConsignacion = $tipoCompra === 'consignacion';
            $cajaPos = null;

            if ($pagaConCajaPos) {
                $cajaPos = Caja::query()
                    ->where('usuario_id', $userId)
                    ->where('estado', 'abierta')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $cajaPos) {
                    throw ValidationException::withMessages([
                        'metodo_pago' => ['Debe tener una caja POS abierta para pagar esta compra con efectivo de caja.'],
                    ]);
                }

                // La fila de caja permanece bloqueada durante toda la compra. Los
                // movimientos también se leen con bloqueo para que dos solicitudes
                // concurrentes no puedan gastar el mismo efectivo disponible.
                $saldoCajaPos = toMoney(
                    (float) MovimientoCaja::query()
                        ->where('caja_id', $cajaPos->id)
                        ->lockForUpdate()
                        ->get(['id', 'monto'])
                        ->sum(fn (MovimientoCaja $movimiento): float => (float) $movimiento->monto),
                    4
                );
                $totalCompraSolicitado = toMoney(
                    collect($validated['items'])->sum(fn (array $item): float => toMoney(
                        (int) $item['cantidad'] * toMoney($item['costo_unitario'])
                    )),
                    4
                );

                if ($saldoCajaPos < $totalCompraSolicitado) {
                    throw ValidationException::withMessages([
                        'metodo_pago' => [sprintf(
                            'Efectivo insuficiente en su caja POS. Disponible: Q %s. Total de la compra: Q %s.',
                            number_format($saldoCajaPos, 2, '.', ','),
                            number_format($totalCompraSolicitado, 2, '.', ',')
                        )],
                    ]);
                }
            }

            $numeroCompra = sprintf(
                'CMP-%s-%03d',
                now()->format('YmdHis'),
                random_int(1, 999)
            );

            $compra = Compra::query()->create([
                'numero' => $numeroCompra,
                'proveedor_id' => $validated['proveedor_id'],
                'fecha_compra' => $validated['fecha_compra'],
                'estado' => 'activo',
                'metodo_pago' => $tipoCompra === 'contado' ? $validated['metodo_pago'] : null,
                'caja_id' => $cajaPos?->id,
                'tipo_compra' => $tipoCompra,
                'es_consignacion' => $esConsignacion,
                'total' => 0,
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'numero_documento' => $validated['numero_documento'] ?? null,
                'factura_receptor' => ($validated['tipo_documento'] ?? null) === 'factura' ? ($validated['factura_receptor'] ?? null) : null,
                'nit_receptor' => $esFacturaFiscal ? $nitEmpresa : null,
                'nit_proveedor' => ($validated['tipo_documento'] ?? null) === 'factura' ? ($validated['nit_proveedor'] ?? null) : null,
                'serie_documento' => ($validated['tipo_documento'] ?? null) === 'factura' ? ($validated['serie_documento'] ?? null) : null,
                'add_user' => $userId,
            ]);

            $total = 0.0;
            $alerts = [];

            foreach ($validated['items'] as $index => $item) {
                $producto = Producto::query()->with('unidadMedida:id,abreviatura')->lockForUpdate()->findOrFail($item['producto_id']);

                if (! $producto->activo) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto {$producto->nombre} esta inactivo y no puede usarse en compras."],
                    ]);
                }

                if ((bool) $producto->control_vencimiento && empty($item['fecha_caducidad'])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.fecha_caducidad" => 'La fecha de caducidad es obligatoria para productos con control de vencimiento.',
                    ]);
                }

                $cantidad = toMoney((int) $item['cantidad'], 4);
                $unidadMedidaSnap = $producto->unidadMedida?->abreviatura ?? 'und';
                $costoUnitario = toMoney($item['costo_unitario']);
                $subtotal = toMoney($cantidad * $costoUnitario);

                $stockAnterior = (float) $producto->stock_actual;
                $stockNuevo = toMoney($stockAnterior + $cantidad, 4);
                $costoAnterior = (float) $producto->costo_promedio;
                $costoPromedioNuevo = weightedAverageCost($stockAnterior, $costoAnterior, $cantidad, $costoUnitario);

                $precioVentaSugerido = $this->calcularPrecioVentaSugerido($costoUnitario, $porcentajeUtilidadCompra);
                $precioVentaAplicado = toMoney($item['precio_venta']);
                $precioVentaPromedioAnterior = (float) $producto->precio_venta_promedio;
                $precioVentaPromedioNuevo = weightedAverageCost($stockAnterior, $precioVentaPromedioAnterior, $cantidad, $precioVentaAplicado);

                if (abs($precioVentaAplicado - $precioVentaSugerido) > 0.0001) {
                    $alerts[] = sprintf(
                        'El precio de venta de %s difiere del sugerido (%.2f vs %.2f).',
                        $producto->nombre,
                        $precioVentaAplicado,
                        $precioVentaSugerido
                    );
                }

                $detalle = CompraDetalle::query()->create([
                    'compra_id'              => $compra->id,
                    'producto_id'            => $producto->id,
                    'cantidad'               => $cantidad,
                    'unidad_medida'          => $unidadMedidaSnap,
                    'costo_unitario'         => $costoUnitario,
                    'subtotal'               => $subtotal,
                    'precio_venta_sugerido'  => $precioVentaSugerido,
                    'precio_venta_aplicado'  => $precioVentaAplicado,
                    'fecha_caducidad'        => $item['fecha_caducidad'] ?? null,
                ]);

                InventarioLote::query()->create([
                    'producto_id'        => $producto->id,
                    'compra_detalle_id'  => $detalle->id,
                    'es_consignacion'    => $esConsignacion,
                    'respaldo_fiscal'    => $esFacturaFiscal ? 'con_factura' : 'sin_factura',
                    'cantidad_inicial'   => $cantidad,
                    'cantidad_disponible' => $cantidad,
                    'costo_unitario'     => $costoUnitario,
                    'fecha_vencimiento'  => $item['fecha_caducidad'] ?? null,
                    'fecha_entrada'      => $validated['fecha_compra'],
                ]);

                InventarioMovimiento::query()->create([
                    'producto_id' => $producto->id,
                    'compra_id' => $compra->id,
                    'compra_detalle_id' => $detalle->id,
                    'tipo' => 'entrada_compra',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'costo_unitario' => $costoUnitario,
                    'referencia' => $compra->numero,
                    'nota' => $item['nota'] ?? null,
                    'add_user' => $userId,
                ]);

                $producto->update([
                    'costo_promedio' => $costoPromedioNuevo,
                    'precio_venta_promedio' => $precioVentaPromedioNuevo,
                    'costo_ultimo'  => $costoUnitario,
                    'stock_actual'   => $stockNuevo,
                    'precio_venta'   => $precioVentaAplicado,
                    'mod_user'       => $userId,
                ]);

                $total += $subtotal;
            }

            $compra->update([
                'total' => toMoney($total),
                'mod_user' => $userId,
            ]);

            if ($esConsignacion) {
                return [
                    'compra' => $compra->load(['proveedor:id,nombre', 'detalles.producto:id,nombre'])->loadCount('detalles'),
                    'alerts' => $alerts,
                ];
            }

            if ($tipoCompra === 'credito_proveedor') {
                ProveedorCuentaPorPagar::query()->create([
                    'proveedor_id' => $compra->proveedor_id,
                    'origen' => 'compra_credito',
                    'compra_id' => $compra->id,
                    'monto_original' => $compra->total,
                    'saldo_pendiente' => $compra->total,
                    'estado' => 'pendiente',
                    'add_user' => $userId,
                ]);

                return [
                    'compra' => $compra->load(['proveedor:id,nombre', 'detalles.producto:id,nombre'])->loadCount('detalles'),
                    'alerts' => $alerts,
                ];
            }

            if ($pagaConCajaPos && $cajaPos) {
                MovimientoCaja::query()->create([
                    'caja_id' => $cajaPos->id,
                    'tipo' => 'compra',
                    'monto' => -1 * toMoney($compra->total),
                    'descripcion' => 'Compra '.$compra->numero,
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'fecha' => $compra->fecha_compra?->toDateTimeString() ?? now()->toDateTimeString(),
                    'usuario_id' => $userId,
                ]);

                return [
                    'compra' => $compra->load(['proveedor:id,nombre', 'detalles.producto:id,nombre'])->loadCount('detalles'),
                    'alerts' => $alerts,
                ];
            }

            $cuentaCapital = capitalCuentaPorCodigo((string) $compra->metodo_pago);
            if (! $cuentaCapital) {
                throw ValidationException::withMessages([
                    'metodo_pago' => ['No existe una cuenta activa para el metodo de pago seleccionado.'],
                ]);
            }

            registrarMovimientoCapital(
                tipo: 'compra',
                monto: (float) $compra->total,
                usuarioId: $userId,
                cuentaOrigenId: (int) $cuentaCapital->id,
                cuentaDestinoId: null,
                descripcion: 'Salida por compra '.$compra->numero,
                fecha: $compra->fecha_compra?->toDateTimeString() ?? now()->toDateTimeString(),
                referenciaTipo: 'compra',
                referenciaId: (int) $compra->id,
                meta: [
                    'metodo_pago' => $compra->metodo_pago,
                ]
            );

            return [
                'compra' => $compra->load(['proveedor:id,nombre', 'detalles.producto:id,nombre'])->loadCount('detalles'),
                'alerts' => $alerts,
            ];
        });

        return response()->json([
            'message' => 'Compra registrada correctamente.',
            'data' => $result['compra'],
            'alerts' => $result['alerts'],
        ], 201);
    }

    private function calcularPrecioVentaSugerido(float $costoUnitario, int $porcentajeUtilidadCompra): float
    {
        $factor = 1 + ($porcentajeUtilidadCompra / 100);

        return toMoney($costoUnitario * $factor);
    }

    private function comprasParaExportar(Request $request): Collection
    {
        $validated = $request->validate([
            'proveedor_id' => ['nullable', Rule::exists('proveedores', 'id')],
            'tipo_documento' => ['nullable', Rule::in(['sin_documento', 'recibo', 'factura'])],
            'factura_receptor' => ['nullable', Rule::in(['cf', 'nit_empresa'])],
        ]);

        return Compra::query()
            ->with('proveedor:id,nombre')
            ->when($validated['proveedor_id'] ?? null, fn ($query, $proveedorId) => $query->where('proveedor_id', $proveedorId))
            ->when($validated['tipo_documento'] ?? null, function ($query, $tipoDocumento) {
                if ($tipoDocumento === 'sin_documento') {
                    $query->where(function ($documentos): void {
                        $documentos->where('tipo_documento', 'sin_documento')->orWhereNull('tipo_documento');
                    });

                    return;
                }

                $query->where('tipo_documento', $tipoDocumento);
            })
            ->when(
                ($validated['tipo_documento'] ?? null) === 'factura' && ! empty($validated['factura_receptor']),
                fn ($query) => $query->where('factura_receptor', $validated['factura_receptor'])
            )
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->get(['id', 'numero', 'proveedor_id', 'fecha_compra', 'estado', 'total', 'tipo_documento', 'factura_receptor', 'numero_documento', 'serie_documento', 'nit_proveedor', 'nit_receptor']);
    }

    private function etiquetasFiltrosExportacion(Request $request): array
    {
        $proveedorId = $request->integer('proveedor_id');
        $tipoDocumento = $request->string('tipo_documento')->toString();
        $facturaReceptor = $request->string('factura_receptor')->toString();

        return [
            'proveedor' => $proveedorId > 0 ? (Proveedor::query()->find($proveedorId)?->nombre ?? 'Proveedor seleccionado') : 'Todos',
            'documento' => match ($tipoDocumento) { 'sin_documento' => 'Sin documento', 'recibo' => 'Recibo', 'factura' => 'Factura', default => 'Todos' },
            'receptor' => $tipoDocumento === 'factura'
                ? match ($facturaReceptor) { 'cf' => 'CF', 'nit_empresa' => 'Mi NIT', default => 'CF y Mi NIT' }
                : null,
        ];
    }

    private function normalizarMontos(Request $request): void
    {
        $items = $request->input('items', []);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (['costo_unitario', 'precio_venta'] as $campo) {
                if (array_key_exists($campo, $item) && $item[$campo] !== '') {
                    $items[$index][$campo] = parseMoney($item[$campo]);
                }
            }
        }

        $request->merge(['items' => $items]);
    }

    private function normalizarDatosFiscales(Request $request): void
    {
        if (! $request->has('nit_proveedor')) {
            return;
        }

        $request->merge([
            'nit_proveedor' => preg_replace('/\D+/', '', (string) $request->input('nit_proveedor')) ?? '',
        ]);
    }

    /**
     * Recalcula únicamente costos derivados del inventario vigente. Los precios
     * de venta normal, mayorista y promedio se conservan para no sobrescribir
     * decisiones comerciales o cambios manuales posteriores a la compra.
     *
     * @return array{costo_promedio: float, costo_ultimo: float}
     */
    private function recalcularCostosProductoTrasAnulacion(
        Producto $producto,
        Compra $compra,
        CompraDetalle $detalle,
        float $stockAnterior,
        float $stockNuevo
    ): array {
        $lotesVigentes = InventarioLote::query()
            ->where('producto_id', $producto->id)
            ->where('cantidad_disponible', '>', 0)
            ->lockForUpdate()
            ->get(['id', 'cantidad_disponible', 'costo_unitario', 'fecha_entrada']);

        $stockEnLotes = (float) $lotesVigentes->sum(
            fn (InventarioLote $lote): float => (float) $lote->cantidad_disponible
        );
        $valorEnLotes = (float) $lotesVigentes->sum(
            fn (InventarioLote $lote): float => (float) $lote->cantidad_disponible * (float) $lote->costo_unitario
        );

        // Compatibilidad con existencias antiguas que todavía no tienen lote.
        $stockSinLote = max($stockNuevo - $stockEnLotes, 0);
        $valorEstimadoRestante = max(
            ($stockAnterior * (float) $producto->costo_promedio)
                - ((float) $detalle->cantidad * (float) $detalle->costo_unitario),
            0
        );
        $valorSinLote = $stockSinLote > 0
            ? max($valorEstimadoRestante - $valorEnLotes, 0)
            : 0;
        $costoPromedio = $stockNuevo > 0
            ? toMoney(($valorEnLotes + $valorSinLote) / $stockNuevo, 4)
            : 0.0;

        $ultimoCostoCompraVigente = CompraDetalle::query()
            ->join('compras', 'compras.id', '=', 'compra_detalles.compra_id')
            ->where('compra_detalles.producto_id', $producto->id)
            ->where('compras.estado', 'activo')
            ->where('compras.id', '!=', $compra->id)
            ->orderByDesc('compras.fecha_compra')
            ->orderByDesc('compras.id')
            ->orderByDesc('compra_detalles.id')
            ->value('compra_detalles.costo_unitario');

        $ultimoCostoLoteVigente = $lotesVigentes
            ->sortByDesc(fn (InventarioLote $lote): string => sprintf(
                '%s-%020d',
                (string) $lote->fecha_entrada,
                (int) $lote->id
            ))
            ->first()?->costo_unitario;

        return [
            'costo_promedio' => $costoPromedio,
            'costo_ultimo' => toMoney(
                $ultimoCostoCompraVigente ?? $ultimoCostoLoteVigente ?? $costoPromedio,
                4
            ),
        ];
    }

    public function anular(Request $request, Compra $compra): JsonResponse
    {
        $userId = (int) $request->user()->id;

        if ($compra->estado !== 'activo') {
            return response()->json([
                'message' => 'Solo se pueden anular compras activas.',
            ], 422);
        }

        DB::transaction(function () use ($compra, $userId): void {
            $compra->loadMissing('detalles');

            foreach ($compra->detalles as $detalle) {
                $producto = Producto::query()->lockForUpdate()->findOrFail($detalle->producto_id);
                $lote = InventarioLote::query()
                    ->where('compra_detalle_id', $detalle->id)
                    ->lockForUpdate()
                    ->first();

                $cantidadDetalle = toMoney($detalle->cantidad, 4);

                if (! $lote) {
                    throw ValidationException::withMessages([
                        'compra' => ["No se encontro lote para el detalle {$detalle->id}; no se puede anular la compra."],
                    ]);
                }

                $disponibleLote = toMoney($lote->cantidad_disponible, 4);
                if ($disponibleLote + 0.0001 < $cantidadDetalle) {
                    throw ValidationException::withMessages([
                        'compra' => [
                            "No se puede anular {$compra->numero}: parte del lote de {$producto->nombre} ya fue consumido.",
                        ],
                    ]);
                }

                $stockAnterior = (float) $producto->stock_actual;
                if ($stockAnterior + 0.0001 < $cantidadDetalle) {
                    throw ValidationException::withMessages([
                        'compra' => [
                            "Stock insuficiente para revertir {$producto->nombre}.",
                        ],
                    ]);
                }

                $stockNuevo = toMoney($stockAnterior - $cantidadDetalle, 4);

                $lote->update([
                    'cantidad_disponible' => toMoney($disponibleLote - $cantidadDetalle, 4),
                ]);

                $costosRecalculados = $this->recalcularCostosProductoTrasAnulacion(
                    $producto,
                    $compra,
                    $detalle,
                    $stockAnterior,
                    $stockNuevo
                );

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'costo_promedio' => $costosRecalculados['costo_promedio'],
                    'costo_ultimo' => $costosRecalculados['costo_ultimo'],
                    'mod_user' => $userId,
                ]);

                InventarioMovimiento::query()->create([
                    'producto_id' => $producto->id,
                    'compra_id' => $compra->id,
                    'compra_detalle_id' => $detalle->id,
                    'tipo' => 'anulacion_compra',
                    'cantidad' => toMoney(-1 * $cantidadDetalle, 4),
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'costo_unitario' => $detalle->costo_unitario,
                    'referencia' => $compra->numero,
                    'nota' => 'Anulacion de compra',
                    'add_user' => $userId,
                ]);
            }

            $compra->update([
                'estado' => 'anulada',
                'mod_user' => $userId,
            ]);

            if ($compra->tipo_compra === 'consignacion' || $compra->es_consignacion) {
                return;
            }

            if ($compra->tipo_compra === 'credito_proveedor') {
                $cuentaPorPagar = ProveedorCuentaPorPagar::query()
                    ->where('compra_id', $compra->id)
                    ->where('origen', 'compra_credito')
                    ->lockForUpdate()
                    ->first();

                if ($cuentaPorPagar && abs((float) $cuentaPorPagar->saldo_pendiente - (float) $cuentaPorPagar->monto_original) > 0.0001) {
                    throw ValidationException::withMessages([
                        'compra' => ['No se puede anular una compra a crédito que ya tiene pagos aplicados.'],
                    ]);
                }

                $cuentaPorPagar?->update([
                    'saldo_pendiente' => 0,
                    'estado' => 'anulada',
                ]);

                return;
            }

            if ($compra->metodo_pago === 'caja_pos') {
                $cajaPos = Caja::query()
                    ->whereKey($compra->caja_id)
                    ->lockForUpdate()
                    ->first();

                if (! $cajaPos) {
                    throw ValidationException::withMessages([
                        'compra' => ['No se encontro la caja POS desde la que se pagó esta compra.'],
                    ]);
                }

                $movimientoOriginal = MovimientoCaja::query()
                    ->where('caja_id', $cajaPos->id)
                    ->where('tipo', 'compra')
                    ->where('referencia_tipo', 'compra')
                    ->where('referencia_id', $compra->id)
                    ->lockForUpdate()
                    ->first();

                if (! $movimientoOriginal) {
                    throw ValidationException::withMessages([
                        'compra' => ['No se encontro el movimiento de caja original de esta compra.'],
                    ]);
                }

                if ($cajaPos->estado !== 'abierta') {
                    $cajaGeneral = capitalCuentaPorCodigo('caja_general');
                    if (! $cajaGeneral) {
                        throw ValidationException::withMessages([
                            'metodo_pago' => ['No existe una Caja general activa para registrar la devolución de esta compra.'],
                        ]);
                    }

                    registrarMovimientoCapital(
                        tipo: 'anulacion_compra',
                        monto: (float) $compra->total,
                        usuarioId: $userId,
                        cuentaOrigenId: null,
                        cuentaDestinoId: (int) $cajaGeneral->id,
                        descripcion: 'Ingreso por anulacion de compra '.$compra->numero.' pagada desde caja POS #'.$cajaPos->id,
                        fecha: now()->toDateTimeString(),
                        referenciaTipo: 'compra',
                        referenciaId: (int) $compra->id,
                        meta: [
                            'metodo_pago_original' => 'caja_pos',
                            'caja_pos_id' => $cajaPos->id,
                        ]
                    );

                    return;
                }

                MovimientoCaja::query()->create([
                    'caja_id' => $cajaPos->id,
                    'tipo' => 'anulacion_compra',
                    'monto' => toMoney($compra->total),
                    'descripcion' => 'Reversion por anulacion de compra '.$compra->numero,
                    'referencia_tipo' => 'compra',
                    'referencia_id' => $compra->id,
                    'fecha' => now()->toDateTimeString(),
                    'usuario_id' => $userId,
                ]);

                return;
            }

            $metodoPago = (string) ($compra->metodo_pago ?: 'caja_general');
            $cuentaCapital = capitalCuentaPorCodigo($metodoPago);
            if (! $cuentaCapital) {
                throw ValidationException::withMessages([
                    'metodo_pago' => ['No existe una cuenta activa para revertir la anulacion de compra.'],
                ]);
            }

            registrarMovimientoCapital(
                tipo: 'anulacion_compra',
                monto: (float) $compra->total,
                usuarioId: $userId,
                cuentaOrigenId: null,
                cuentaDestinoId: (int) $cuentaCapital->id,
                descripcion: 'Reversion por anulacion de compra '.$compra->numero,
                fecha: now()->toDateTimeString(),
                referenciaTipo: 'compra',
                referenciaId: (int) $compra->id,
                meta: [
                    'metodo_pago' => $metodoPago,
                ]
            );
        });

        return response()->json([
            'message' => 'Compra anulada correctamente.',
            'data' => $compra->fresh(['proveedor:id,nombre'])->loadCount('detalles'),
        ]);
    }
}
