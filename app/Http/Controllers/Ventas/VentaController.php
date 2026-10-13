<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ProveedorCuentaPorPagar;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaCredito;
use App\Models\VentaCreditoMovimiento;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VentaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $desde = $request->filled('desde')
            ? Carbon::parse((string) $request->input('desde'))->toDateString()
            : Carbon::today()->toDateString();
        $hasta = $request->filled('hasta')
            ? Carbon::parse((string) $request->input('hasta'))->toDateString()
            : $desde;

        $ventas = Venta::query()
            ->with(['cliente:id,nombre'])
            ->withCount('detalles')
            ->where('add_user', (int) $request->user()->id)
            ->whereBetween('fecha_venta', [$desde, $hasta])
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->get([
                'id',
                'numero',
                'cliente_id',
                'fecha_venta',
                'estado',
                'metodo_pago',
                'subtotal',
                'descuento',
                'total',
                'monto_recibido',
                'cambio',
                'created_at',
            ]);

        return response()->json([
            'data' => $ventas,
            'meta' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ]);
    }

    public function historial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'usuario_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $user = $request->user()?->loadMissing('role:id,code');
        $isAdmin = $user?->role?->code === 'admin';

        $desde = ! empty($validated['desde'])
            ? Carbon::parse((string) $validated['desde'])->toDateString()
            : Carbon::today()->toDateString();
        $hasta = ! empty($validated['hasta'])
            ? Carbon::parse((string) $validated['hasta'])->toDateString()
            : $desde;

        $ventas = Venta::query()
            ->with([
                'cliente:id,nombre',
                'usuario:id,name,username,activo,deleted_at',
            ])
            ->withCount('detalles')
            ->whereBetween('fecha_venta', [$desde, $hasta])
            ->when(
                ! $isAdmin,
                fn ($query) => $query->where('add_user', (int) $user?->id),
                function ($query) use ($validated): void {
                    if (! empty($validated['usuario_id'])) {
                        $query->where('add_user', (int) $validated['usuario_id']);
                    }
                }
            )
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->get([
                'id',
                'numero',
                'cliente_id',
                'fecha_venta',
                'estado',
                'metodo_pago',
                'subtotal',
                'descuento',
                'total',
                'monto_recibido',
                'cambio',
                'created_at',
                'add_user',
            ]);

        return response()->json([
            'data' => $ventas,
            'meta' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'scope' => $isAdmin ? 'global' : 'user',
                'can_view_all' => $isAdmin,
                'usuario_id' => $isAdmin ? ($validated['usuario_id'] ?? null) : (int) $user?->id,
                'usuarios' => $isAdmin
                    ? User::query()
                        ->withTrashed()
                        ->orderBy('name')
                        ->get(['id', 'name', 'username', 'activo', 'deleted_at'])
                    : [],
            ],
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $clientes = Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit', 'tipo_cliente']);

        $consumidorFinalId = Cliente::query()
            ->where('activo', true)
            ->where('nit', 'CF')
            ->value('id');

        $productos = Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'codigo_barra',
                'palabras_clave',
                'precio_venta',
                'precio_mayorista',
                'stock_actual',
                'unidad_medida_id',
            ]);

        $cajaActiva = Caja::query()
            ->where('usuario_id', (int) $request->user()->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first(['id']);

        return response()->json([
            'data' => [
                'clientes' => $clientes,
                'consumidor_final_id' => $consumidorFinalId,
                'productos' => $productos,
                'metodos_pago' => ['efectivo', 'credito'],
                'caja_activa' => $cajaActiva,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')],
            'fecha_venta' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'credito'])],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_venta'],
            'descuento' => ['nullable', 'numeric', 'gte:0'],
            'monto_recibido' => ['nullable', 'numeric', 'gte:0'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $cliente = null;
        if (! empty($validated['cliente_id'])) {
            $cliente = Cliente::query()->find($validated['cliente_id']);
            if ($cliente && ! $cliente->activo) {
                throw ValidationException::withMessages([
                    'cliente_id' => ['El cliente seleccionado esta inactivo y no puede usarse en nuevas ventas.'],
                ]);
            }
        }

        if ($validated['metodo_pago'] === 'credito') {
            if (empty($validated['cliente_id'])) {
                throw ValidationException::withMessages([
                    'cliente_id' => ['Debe seleccionar un cliente para una venta al crédito.'],
                ]);
            }

        }

        if ($validated['metodo_pago'] === 'efectivo') {
            $cajaActiva = Caja::query()
                ->where('usuario_id', (int) getUserId())
                ->where('estado', 'abierta')
                ->latest('id')
                ->first(['id']);

            if (! $cajaActiva) {
                throw ValidationException::withMessages([
                    'caja' => ['Debe tener una caja abierta para registrar ventas en efectivo.'],
                ]);
            }
        }

        $tipoCliente = $cliente?->tipo_cliente === 'mayorista' ? 'mayorista' : 'minorista';

        $result = DB::transaction(function () use ($validated, $tipoCliente) {
            $numeroVenta = sprintf(
                'VTA-%s-%03d',
                now()->format('YmdHis'),
                random_int(1, 999)
            );

            $venta = Venta::query()->create([
                'numero' => $numeroVenta,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'fecha_venta' => $validated['fecha_venta'],
                'estado' => 'activo',
                'metodo_pago' => $validated['metodo_pago'],
                'subtotal' => 0,
                'descuento' => toMoney($validated['descuento'] ?? 0, 4),
                'total' => 0,
                'monto_recibido' => $validated['monto_recibido'] ?? null,
                'cambio' => null,
                'observaciones' => $validated['observaciones'] ?? null,
                'add_user' => getUserId(),
            ]);

            $subtotal = 0.0;

            foreach ($validated['items'] as $item) {
                $producto = Producto::query()->with('unidadMedida:id,abreviatura')->lockForUpdate()->findOrFail($item['producto_id']);

                if (! $producto->activo) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto {$producto->nombre} esta inactivo y no puede venderse."],
                    ]);
                }

                $cantidad = toMoney((int) $item['cantidad'], 4);
                $precioBase = $tipoCliente === 'mayorista' && (float) $producto->precio_mayorista > 0
                    ? $producto->precio_mayorista
                    : $producto->precio_venta;
                $precio = toMoney($precioBase, 4);
                $lineSubtotal = toMoney($cantidad * $precio, 4);
                $stockAnterior = (float) $producto->stock_actual;

                if ($stockAnterior + 0.0001 < $cantidad) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para {$producto->nombre}. Disponible: {$stockAnterior}"],
                    ]);
                }

                $remaining = $cantidad;
                $consumosLote = [];
                $lotes = InventarioLote::query()
                    ->with('compraDetalle.compra')
                    ->where('producto_id', $producto->id)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderBy('fecha_entrada')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lotes as $lote) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $disponible = (float) $lote->cantidad_disponible;
                    if ($disponible <= 0) {
                        continue;
                    }

                    $usar = min($remaining, $disponible);
                    $lote->update([
                        'cantidad_disponible' => toMoney($disponible - $usar, 4),
                    ]);

                    $consumosLote[] = [
                        'lote_id' => (int) $lote->id,
                        'cantidad' => (float) toMoney($usar, 4),
                        'costo_unitario' => (float) toMoney($lote->costo_unitario, 4),
                    ];

                    $remaining = toMoney($remaining - $usar, 4);
                }

                if ($remaining > 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => ["No existen lotes suficientes para {$producto->nombre}. Faltante: {$remaining}"],
                    ]);
                }

                $unidadMedida = null;
                if ($producto->relationLoaded('unidadMedida')) {
                    $unidadMedida = $producto->unidadMedida?->abreviatura;
                }

                $detalle = VentaDetalle::query()->create([
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'unidad_medida' => $unidadMedida,
                    'precio_unitario' => $precio,
                    'subtotal' => $lineSubtotal,
                ]);

                $stockNuevo = toMoney($stockAnterior - $cantidad, 4);

                $stockCursor = (float) $stockAnterior;
                foreach ($consumosLote as $consumoLote) {
                    $cantidadConsumida = toMoney((float) $consumoLote['cantidad'], 4);
                    $stockDespues = toMoney($stockCursor - $cantidadConsumida, 4);

                    $movimiento = InventarioMovimiento::query()->create([
                        'producto_id' => $producto->id,
                        'venta_id' => $venta->id,
                        'venta_detalle_id' => $detalle->id,
                        'lote_id' => (int) $consumoLote['lote_id'],
                        'tipo' => 'salida_venta',
                        'cantidad' => toMoney(-1 * $cantidadConsumida, 4),
                        'stock_anterior' => $stockCursor,
                        'stock_nuevo' => $stockDespues,
                        'costo_unitario' => (float) $consumoLote['costo_unitario'],
                        'referencia' => $venta->numero,
                        'nota' => 'Venta registrada',
                        'add_user' => getUserId(),
                    ]);

                    $lote = $lotes->firstWhere('id', (int) $consumoLote['lote_id']);
                    $compra = $lote?->compraDetalle?->compra;
                    if ($lote?->es_consignacion && $compra?->es_consignacion) {
                        ProveedorCuentaPorPagar::query()->create([
                            'proveedor_id' => $compra->proveedor_id,
                            'origen' => 'consignacion_venta',
                            'compra_id' => $compra->id,
                            'compra_detalle_id' => $lote->compra_detalle_id,
                            'venta_id' => $venta->id,
                            'venta_detalle_id' => $detalle->id,
                            'inventario_movimiento_id' => $movimiento->id,
                            'cantidad' => $cantidadConsumida,
                            'costo_unitario' => $consumoLote['costo_unitario'],
                            'monto_original' => toMoney($cantidadConsumida * (float) $consumoLote['costo_unitario'], 4),
                            'saldo_pendiente' => toMoney($cantidadConsumida * (float) $consumoLote['costo_unitario'], 4),
                            'estado' => 'pendiente',
                            'add_user' => getUserId(),
                        ]);
                    }

                    $stockCursor = (float) $stockDespues;
                }

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'mod_user' => getUserId(),
                ]);

                $subtotal += $lineSubtotal;
            }

            $descuento = toMoney($venta->descuento, 4);
            $total = toMoney(max(0, $subtotal - $descuento), 4);

            $montoRecibido = $venta->metodo_pago === 'efectivo'
                ? ($validated['monto_recibido'] ?? null)
                : null;
            $cambio = null;

            if ($venta->metodo_pago === 'efectivo') {
                $montoRecibidoFloat = toMoney($montoRecibido, 4);
                if ($montoRecibidoFloat + 0.0001 < $total) {
                    throw ValidationException::withMessages([
                        'monto_recibido' => ['El monto recibido no puede ser menor al total.'],
                    ]);
                }
                $cambio = toMoney($montoRecibidoFloat - $total, 4);
                $montoRecibido = $montoRecibidoFloat;
            }

            $venta->update([
                'subtotal' => toMoney($subtotal, 4),
                'total' => $total,
                'monto_recibido' => $montoRecibido,
                'cambio' => $cambio,
                'mod_user' => getUserId(),
            ]);

            if ($venta->metodo_pago === 'credito') {
                $credito = VentaCredito::query()->create([
                    'venta_id' => $venta->id,
                    'cliente_id' => $venta->cliente_id,
                    'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                    'monto_original' => $total,
                    'saldo_pendiente' => $total,
                    'estado' => 'activa',
                    'observaciones' => $venta->observaciones,
                    'add_user' => getUserId(),
                    'mod_user' => getUserId(),
                ]);

                VentaCreditoMovimiento::query()->create([
                    'venta_credito_id' => $credito->id,
                    'tipo' => 'cargo_inicial',
                    'monto' => $total,
                    'saldo_anterior' => 0,
                    'saldo_posterior' => $total,
                    'fecha' => now(),
                    'descripcion' => 'Venta a crédito '.$venta->numero,
                    'usuario_id' => getUserId(),
                ]);
            }

            if ($venta->metodo_pago === 'efectivo' && getUserId()) {
                $movimientoCaja = registrarMovimientoCajaAutomatico(
                    getUserId(),
                    'venta',
                    $total,
                    'Ingreso por venta '.$venta->numero,
                    $venta->fecha_venta?->toDateString(),
                    'venta',
                    $venta->id
                );

                if (! $movimientoCaja) {
                    throw ValidationException::withMessages([
                        'caja' => ['Debe tener una caja abierta para registrar ventas.'],
                    ]);
                }
            }

            return $venta->load(['cliente:id,nombre', 'detalles.producto:id,nombre']);
        });

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'data' => $result,
        ], 201);
    }

    public function anular(Request $request, Venta $venta): JsonResponse
    {
        if ((int) $venta->add_user !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado para anular esta venta.',
            ], 403);
        }

        $fechaVenta = Carbon::parse((string) $venta->fecha_venta)->toDateString();
        $hoy = Carbon::today()->toDateString();
        if ($fechaVenta !== $hoy) {
            return response()->json([
                'message' => 'Solo se pueden anular ventas del mismo dia. Para fechas anteriores utilice devolucion.',
            ], 422);
        }

        if ($venta->estado !== 'activo') {
            return response()->json([
                'message' => 'Solo se pueden anular ventas activas.',
            ], 422);
        }

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($venta, $userId): void {
            $venta = Venta::query()
                ->with([
                    'detalles:id,venta_id,producto_id,cantidad,precio_unitario,subtotal',
                    'detalles.producto:id,nombre,stock_actual,costo_promedio,control_vencimiento',
                ])
                ->lockForUpdate()
                ->findOrFail($venta->id);

            $credito = VentaCredito::query()
                ->where('venta_id', $venta->id)
                ->lockForUpdate()
                ->first();

            if ($credito && abs((float) $credito->saldo_pendiente - (float) $credito->monto_original) > 0.0001) {
                throw ValidationException::withMessages([
                    'venta' => ['No se puede anular una venta a crédito que ya tiene abonos.'],
                ]);
            }

            $cargosProveedor = ProveedorCuentaPorPagar::query()->where('venta_id', $venta->id)->lockForUpdate()->get();
            if ($cargosProveedor->contains(fn ($cargo) => abs((float) $cargo->saldo_pendiente - (float) $cargo->monto_original) > 0.0001)) {
                throw ValidationException::withMessages(['venta' => ['No se puede anular una venta consignada que ya tiene pagos aplicados al proveedor.']]);
            }

            $devolucionesActivas = $venta->devoluciones()->where('estado', 'activo')->count();
            if ($devolucionesActivas > 0) {
                throw ValidationException::withMessages([
                    'venta' => ['No se puede anular la venta porque tiene devoluciones activas asociadas.'],
                ]);
            }

            foreach ($venta->detalles as $detalle) {
                $producto = Producto::query()->lockForUpdate()->findOrFail($detalle->producto_id);
                $cantidad = toMoney($detalle->cantidad, 4);

                $stockAnterior = (float) $producto->stock_actual;
                $movimientosSalida = InventarioMovimiento::query()
                    ->where('tipo', 'salida_venta')
                    ->where('venta_detalle_id', $detalle->id)
                    ->whereNotNull('lote_id')
                    ->orderBy('id')
                    ->get(['lote_id', 'cantidad', 'costo_unitario']);

                $consumoTrazado = (float) $movimientosSalida->sum(fn ($mov) => abs((float) $mov->cantidad));
                $usarTrazabilidadLote = $movimientosSalida->isNotEmpty() && abs($consumoTrazado - (float) $cantidad) <= 0.0001;

                if ($usarTrazabilidadLote) {
                    $stockCursor = (float) $stockAnterior;

                    foreach ($movimientosSalida as $movSalida) {
                        $cantidadReponer = toMoney(abs((float) $movSalida->cantidad), 4);
                        if ($cantidadReponer <= 0) {
                            continue;
                        }

                        $lote = InventarioLote::query()->lockForUpdate()->find($movSalida->lote_id);
                        if (! $lote) {
                            throw ValidationException::withMessages([
                                'venta' => ['No se encontro el lote original consumido para anular esta venta.'],
                            ]);
                        }

                        $disponible = (float) $lote->cantidad_disponible;
                        $lote->update([
                            'cantidad_disponible' => toMoney($disponible + $cantidadReponer, 4),
                        ]);

                        $stockDespues = toMoney($stockCursor + $cantidadReponer, 4);

                        InventarioMovimiento::query()->create([
                            'producto_id' => $producto->id,
                            'venta_id' => $venta->id,
                            'venta_detalle_id' => $detalle->id,
                            'lote_id' => (int) $movSalida->lote_id,
                            'tipo' => 'anulacion_venta',
                            'cantidad' => $cantidadReponer,
                            'stock_anterior' => $stockCursor,
                            'stock_nuevo' => $stockDespues,
                            'costo_unitario' => toMoney((float) $movSalida->costo_unitario, 4),
                            'referencia' => $venta->numero,
                            'nota' => 'Anulacion de venta',
                            'add_user' => $userId,
                        ]);

                        $stockCursor = (float) $stockDespues;
                    }

                    $stockNuevo = toMoney($stockCursor, 4);
                } else {
                    $stockNuevo = toMoney($stockAnterior + $cantidad, 4);
                    $loteCreadoId = null;

                    if ((bool) $producto->control_vencimiento) {
                        $lote = InventarioLote::query()->create([
                            'producto_id' => $producto->id,
                            'compra_detalle_id' => null,
                            'cantidad_inicial' => $cantidad,
                            'cantidad_disponible' => $cantidad,
                            'costo_unitario' => toMoney($producto->costo_promedio, 4),
                            'fecha_vencimiento' => null,
                            'fecha_entrada' => now()->toDateString(),
                        ]);
                        $loteCreadoId = (int) $lote->id;
                    }

                    InventarioMovimiento::query()->create([
                        'producto_id' => $producto->id,
                        'venta_id' => $venta->id,
                        'venta_detalle_id' => $detalle->id,
                        'lote_id' => $loteCreadoId,
                        'tipo' => 'anulacion_venta',
                        'cantidad' => $cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'costo_unitario' => $producto->costo_promedio,
                        'referencia' => $venta->numero,
                        'nota' => 'Anulacion de venta',
                        'add_user' => $userId,
                    ]);
                }

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'mod_user' => $userId,
                ]);
            }

            $venta->update([
                'estado' => 'anulada',
                'mod_user' => $userId,
            ]);

            $cargosProveedor->each(fn (ProveedorCuentaPorPagar $cargo) => $cargo->update(['saldo_pendiente' => 0, 'estado' => 'anulada']));

            if ($credito) {
                $saldoAnterior = toMoney($credito->saldo_pendiente, 4);
                $credito->update([
                    'saldo_pendiente' => 0,
                    'estado' => 'anulada',
                    'mod_user' => $userId,
                ]);
                VentaCreditoMovimiento::query()->create([
                    'venta_credito_id' => $credito->id,
                    'tipo' => 'anulacion',
                    'monto' => $saldoAnterior,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_posterior' => 0,
                    'fecha' => now(),
                    'descripcion' => 'Anulación de venta '.$venta->numero,
                    'usuario_id' => $userId,
                ]);
            }

            if ($venta->metodo_pago === 'efectivo') {
                registrarMovimientoCajaAutomatico(
                    $userId,
                    'anulacion_venta',
                    toMoney(-1 * (float) $venta->total, 4),
                    'Anulacion de venta '.$venta->numero,
                    now()->toDateTimeString(),
                    'venta',
                    $venta->id
                );
            }
        });

        return response()->json([
            'message' => 'Venta anulada correctamente.',
            'data' => $venta->fresh(['cliente:id,nombre'])->loadCount('detalles'),
        ]);
    }

    public function ticketSignedUrl(Request $request, Venta $venta): JsonResponse
    {
        $user = $request->user()?->loadMissing('role:id,code');
        $isAdmin = $user?->role?->code === 'admin';

        if (! $isAdmin && (int) $venta->add_user !== (int) $user?->id) {
            return response()->json(['message' => 'No autorizado para ver este ticket.'], 403);
        }

        $url = URL::temporarySignedRoute(
            'api.ventas.ticket',
            now()->addMinutes(30),
            ['venta' => $venta->id],
            absolute: false
        );

        return response()->json(['url' => $url]);
    }

    public function ticket(Request $request, Venta $venta): Response
    {
        $venta->loadMissing([
            'cliente:id,nombre,nit',
            'detalles.producto:id,nombre',
        ]);

        $lineas = max(12, $venta->detalles->count() + 12);
        $altoPuntos = max(430, (int) round($lineas * 22));

        $pdf = Pdf::loadView('tickets.venta', [
            'venta' => $venta,
            'empresa' => [
                'nombre' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
                'telefono' => Configuracion::valor('telefono_empresa', null),
                'direccion' => Configuracion::valor('direccion_empresa', null),
            ],
        ])->setPaper([0, 0, 226.77, $altoPuntos], 'portrait');

        return $pdf->stream('ticket-venta-'.$venta->numero.'.pdf');
    }
}
