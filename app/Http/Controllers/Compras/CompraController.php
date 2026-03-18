<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\CapitalCuenta;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Configuracion;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                'total',
                'tipo_documento',
                'numero_documento',
                'created_at',
            ]);

        return response()->json([
            'data' => $compras,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $categorias = Categoria::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $proveedores = Proveedor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

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

        return response()->json([
            'data' => [
                'categorias'          => $categorias,
                'proveedores'         => $proveedores,
                'proveedor_general_id' => $proveedorGeneral,
                'porcentaje_utilidad_compra' => (int) Configuracion::valor('porcentaje_utilidad_compra', 25),
                'metodos_pago' => [
                    ['value' => 'caja_general', 'label' => 'Caja general'],
                    ['value' => 'banco', 'label' => 'Banco'],
                ],
                'capital_cuentas' => CapitalCuenta::query()
                    ->whereIn('codigo', ['caja_general', 'banco'])
                    ->where('activo', true)
                    ->orderBy('id')
                    ->get(['id', 'codigo', 'nombre', 'saldo_actual']),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'proveedor_id' => ['required', Rule::exists('proveedores', 'id')],
                'fecha_compra' => ['required', 'date'],
                'metodo_pago' => ['required', Rule::in(['caja_general', 'banco'])],
                'tipo_documento' => ['nullable', Rule::in(['sin_documento', 'recibo', 'factura'])],
                'numero_documento' => ['nullable', 'string', 'max:100'],
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

        $proveedorActivo = Proveedor::query()
            ->whereKey($validated['proveedor_id'])
            ->where('activo', true)
            ->exists();

        if (! $proveedorActivo) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor seleccionado esta inactivo y no puede usarse en compras.',
            ]);
        }

        $porcentajeUtilidadCompra = max(0, (int) Configuracion::valor('porcentaje_utilidad_compra', 25));
        $userId = (int) $request->user()->id;

        $result = DB::transaction(function () use ($validated, $porcentajeUtilidadCompra, $userId) {
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
                'metodo_pago' => $validated['metodo_pago'],
                'total' => 0,
                'tipo_documento' => $validated['tipo_documento'] ?? null,
                'numero_documento' => $validated['numero_documento'] ?? null,
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
                $costoUnitario = toMoney($item['costo_unitario'], 4);
                $subtotal = toMoney($cantidad * $costoUnitario, 4);

                $stockAnterior = (float) $producto->stock_actual;
                $stockNuevo = toMoney($stockAnterior + $cantidad, 4);
                $costoAnterior = (float) $producto->costo_promedio;
                $costoPromedioNuevo = weightedAverageCost($stockAnterior, $costoAnterior, $cantidad, $costoUnitario);

                $precioVentaSugerido = $this->calcularPrecioVentaSugerido($costoUnitario, $porcentajeUtilidadCompra);
                $precioVentaAplicado = toMoney($item['precio_venta'], 4);
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
                'total' => toMoney($total, 4),
                'mod_user' => $userId,
            ]);

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

        return toMoney($costoUnitario * $factor, 4);
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

                $producto->update([
                    'stock_actual' => $stockNuevo,
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
