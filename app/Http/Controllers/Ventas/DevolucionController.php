<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\DetalleDevolucion;
use App\Models\Devolucion;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DevolucionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $desde = ! empty($validated['desde'])
            ? Carbon::parse((string) $validated['desde'])->toDateString()
            : Carbon::today()->toDateString();
        $hasta = ! empty($validated['hasta'])
            ? Carbon::parse((string) $validated['hasta'])->toDateString()
            : $desde;

        $items = Devolucion::query()
            ->with([
                'venta:id,numero,fecha_venta',
                'usuario:id,name',
                'detalles.producto:id,nombre',
            ])
            ->where('usuario_id', (int) $request->user()->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'venta_id', 'usuario_id', 'fecha', 'estado', 'total', 'created_at']);

        return response()->json([
            'data' => $items,
            'meta' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role:id,code');
        $limiteDiasCajero = $this->limiteDiasCajero();
        $limiteMinutosCajero = $limiteDiasCajero * 24 * 60;

        $ventas = Venta::query()
            ->with([
                'cliente:id,nombre',
                'detalles:id,venta_id,producto_id,cantidad,precio_unitario,subtotal',
                'detalles.producto:id,nombre,stock_actual,costo_promedio,control_vencimiento',
            ])
            ->where('add_user', (int) $user->id)
            ->where('estado', 'activo')
            ->whereDate('fecha_venta', '<', Carbon::today()->toDateString())
            ->when($user->role?->code === 'cajero', function ($query) use ($limiteMinutosCajero): void {
                $query->where('created_at', '>=', now()->subMinutes($limiteMinutosCajero));
            })
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->limit(120)
            ->get(['id', 'numero', 'cliente_id', 'fecha_venta', 'total']);

        $detalleIds = $ventas->flatMap(fn (Venta $venta) => $venta->detalles->pluck('id'))->unique()->values();

        $devueltoByDetalle = collect();
        if ($detalleIds->isNotEmpty()) {
            $devueltoByDetalle = DetalleDevolucion::query()
                ->whereIn('venta_detalle_id', $detalleIds)
                ->whereHas('devolucion', fn ($q) => $q->where('estado', 'activo'))
                ->get(['venta_detalle_id', 'cantidad'])
                ->groupBy('venta_detalle_id')
                ->map(fn ($rows) => (float) $rows->sum('cantidad'));
        }

        $ventas->each(function (Venta $venta) use ($devueltoByDetalle): void {
            $venta->detalles->transform(function ($detalle) use ($devueltoByDetalle) {
                $devuelto = (float) ($devueltoByDetalle->get($detalle->id, 0));
                $detalle->setAttribute('cantidad_devuelta', toMoney($devuelto, 4));
                $detalle->setAttribute('cantidad_disponible_devolucion', toMoney((float) $detalle->cantidad - $devuelto, 4));
                return $detalle;
            });
        });

        return response()->json([
            'data' => [
                'ventas' => $ventas,
                'limite_dias_cajero' => $limiteDiasCajero,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venta_id' => ['required', Rule::exists('ventas', 'id')],
            'fecha' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.venta_detalle_id' => ['required', Rule::exists('venta_detalles', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user()->loadMissing('role:id,code');
        $userId = (int) $user->id;
        $limiteDiasCajero = $this->limiteDiasCajero();
        $limiteMinutosCajero = $limiteDiasCajero * 24 * 60;

        $devolucion = DB::transaction(function () use ($validated, $userId, $user, $limiteMinutosCajero, $limiteDiasCajero) {
            $venta = Venta::query()
                ->with('detalles.producto:id,nombre,stock_actual,costo_promedio,control_vencimiento')
                ->lockForUpdate()
                ->findOrFail($validated['venta_id']);

            if ((int) $venta->add_user !== $userId) {
                throw ValidationException::withMessages([
                    'venta_id' => ['La venta seleccionada no pertenece al usuario en sesion.'],
                ]);
            }

            if ($venta->estado !== 'activo') {
                throw ValidationException::withMessages([
                    'venta_id' => ['Solo se permiten devoluciones sobre ventas activas.'],
                ]);
            }

            if (Carbon::parse((string) $venta->fecha_venta)->isSameDay(Carbon::today())) {
                throw ValidationException::withMessages([
                    'venta_id' => ['Las ventas del mismo dia deben manejarse con anulacion de venta.'],
                ]);
            }

            if ($user->role?->code === 'cajero') {
                $fechaLimite = now()->subMinutes($limiteMinutosCajero);
                if ($venta->created_at && $venta->created_at->lt($fechaLimite)) {
                    throw ValidationException::withMessages([
                        'venta_id' => [
                            "El tiempo maximo para devolucion en rol cajero es de {$limiteDiasCajero} dia(s).",
                        ],
                    ]);
                }
            }

            $detallesVenta = $venta->detalles->keyBy('id');

            $devolucion = Devolucion::query()->create([
                'venta_id' => $venta->id,
                'usuario_id' => $userId,
                'fecha' => $validated['fecha'],
                'estado' => 'activo',
                'total' => 0,
            ]);

            $total = 0.0;

            foreach ($validated['items'] as $item) {
                $detalleVenta = $detallesVenta->get((int) $item['venta_detalle_id']);

                if (! $detalleVenta) {
                    throw ValidationException::withMessages([
                        'items' => ['Uno de los detalles no pertenece a la venta seleccionada.'],
                    ]);
                }

                $yaDevuelto = (float) DetalleDevolucion::query()
                    ->where('venta_detalle_id', $detalleVenta->id)
                    ->whereHas('devolucion', fn ($q) => $q->where('estado', 'activo'))
                    ->sum('cantidad');

                $cantidad = toMoney((int) $item['cantidad'], 4);
                $disponible = toMoney((float) $detalleVenta->cantidad - $yaDevuelto, 4);

                if ($cantidad > $disponible + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => ["La cantidad a devolver de {$detalleVenta->producto->nombre} excede lo disponible ({$disponible})."],
                    ]);
                }

                $precio = toMoney($detalleVenta->precio_unitario, 4);
                $subtotal = toMoney($cantidad * $precio, 4);

                $detalleDevolucion = DetalleDevolucion::query()->create([
                    'devolucion_id' => $devolucion->id,
                    'venta_detalle_id' => $detalleVenta->id,
                    'producto_id' => $detalleVenta->producto_id,
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                    'motivo' => $item['motivo'] ?? null,
                ]);

                $producto = Producto::query()->lockForUpdate()->findOrFail($detalleVenta->producto_id);
                $stockAnterior = (float) $producto->stock_actual;
                $movimientosSalida = InventarioMovimiento::query()
                    ->where('tipo', 'salida_venta')
                    ->where('venta_detalle_id', $detalleVenta->id)
                    ->whereNotNull('lote_id')
                    ->orderBy('id')
                    ->get(['lote_id', 'cantidad', 'costo_unitario']);

                $devueltoPorLote = InventarioMovimiento::query()
                    ->whereIn('tipo', ['devolucion_venta', 'anulacion_devolucion'])
                    ->where('venta_detalle_id', $detalleVenta->id)
                    ->whereNotNull('lote_id')
                    ->selectRaw('lote_id, COALESCE(SUM(cantidad), 0) as neto_devuelto')
                    ->groupBy('lote_id')
                    ->get()
                    ->keyBy('lote_id');

                $restanteDevolver = toMoney($cantidad, 4);
                $stockCursor = (float) $stockAnterior;

                foreach ($movimientosSalida as $movSalida) {
                    if ($restanteDevolver <= 0) {
                        break;
                    }

                    $consumidoLote = toMoney(abs((float) $movSalida->cantidad), 4);
                    if ($consumidoLote <= 0) {
                        continue;
                    }

                    $netoDevuelto = (float) ($devueltoPorLote->get($movSalida->lote_id)->neto_devuelto ?? 0);
                    $yaDevueltoLote = toMoney(max(0, $netoDevuelto), 4);
                    $disponibleReponerLote = toMoney(max(0, $consumidoLote - $yaDevueltoLote), 4);
                    if ($disponibleReponerLote <= 0) {
                        continue;
                    }

                    $usar = toMoney(min($restanteDevolver, $disponibleReponerLote), 4);
                    if ($usar <= 0) {
                        continue;
                    }

                    $lote = InventarioLote::query()->lockForUpdate()->find($movSalida->lote_id);
                    if (! $lote) {
                        throw ValidationException::withMessages([
                            'items' => ['No se encontro un lote original consumido para aplicar la devolucion.'],
                        ]);
                    }

                    $disponibleActual = (float) $lote->cantidad_disponible;
                    $lote->update([
                        'cantidad_disponible' => toMoney($disponibleActual + $usar, 4),
                    ]);

                    $stockDespues = toMoney($stockCursor + $usar, 4);

                    InventarioMovimiento::query()->create([
                        'producto_id' => $producto->id,
                        'venta_id' => $venta->id,
                        'venta_detalle_id' => $detalleDevolucion->venta_detalle_id,
                        'lote_id' => (int) $movSalida->lote_id,
                        'tipo' => 'devolucion_venta',
                        'cantidad' => $usar,
                        'stock_anterior' => $stockCursor,
                        'stock_nuevo' => $stockDespues,
                        'costo_unitario' => toMoney((float) $movSalida->costo_unitario, 4),
                        'referencia' => 'DEV-'.$devolucion->id,
                        'nota' => $detalleDevolucion->motivo,
                        'add_user' => $userId,
                    ]);

                    $stockCursor = (float) $stockDespues;
                    $restanteDevolver = toMoney($restanteDevolver - $usar, 4);
                }

                if ($movimientosSalida->isNotEmpty() && $restanteDevolver > 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => ['No se pudo aplicar la devolucion sobre los lotes originales consumidos.'],
                    ]);
                }

                if ($movimientosSalida->isEmpty()) {
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
                            'fecha_entrada' => $validated['fecha'],
                        ]);
                        $loteCreadoId = (int) $lote->id;
                    }

                    InventarioMovimiento::query()->create([
                        'producto_id' => $producto->id,
                        'venta_id' => $venta->id,
                        'venta_detalle_id' => $detalleDevolucion->venta_detalle_id,
                        'lote_id' => $loteCreadoId,
                        'tipo' => 'devolucion_venta',
                        'cantidad' => $cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'costo_unitario' => $producto->costo_promedio,
                        'referencia' => 'DEV-'.$devolucion->id,
                        'nota' => $detalleDevolucion->motivo,
                        'add_user' => $userId,
                    ]);

                    $stockCursor = (float) $stockNuevo;
                }

                $producto->update([
                    'stock_actual' => toMoney($stockCursor, 4),
                    'mod_user' => $userId,
                ]);

                $total += $subtotal;
            }

            $devolucion->update([
                'total' => toMoney($total, 4),
            ]);

            return $devolucion;
        });

        return response()->json([
            'message' => 'Devolucion registrada correctamente.',
            'data' => $devolucion->load([
                'venta:id,numero,fecha_venta',
                'usuario:id,name',
                'detalles.producto:id,nombre',
            ]),
        ], 201);
    }

    private function limiteDiasCajero(): int
    {
        $valor = (int) Configuracion::valor('devolucion_limite_dias_cajero', 15);

        return max(2, $valor);
    }

    public function anular(Request $request, Devolucion $devolucion): JsonResponse
    {
        if ((int) $devolucion->usuario_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'No autorizado para anular esta devolucion.',
            ], 403);
        }

        if ($devolucion->estado !== 'activo') {
            return response()->json([
                'message' => 'Solo se pueden anular devoluciones activas.',
            ], 422);
        }

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($devolucion, $userId): void {
            $devolucion = Devolucion::query()
                ->with(['detalles:id,devolucion_id,venta_detalle_id,producto_id,cantidad'])
                ->lockForUpdate()
                ->findOrFail($devolucion->id);

            foreach ($devolucion->detalles as $detalle) {
                $producto = Producto::query()->lockForUpdate()->findOrFail($detalle->producto_id);
                $cantidad = toMoney($detalle->cantidad, 4);
                $stockAnterior = (float) $producto->stock_actual;

                if ($stockAnterior + 0.0001 < $cantidad) {
                    throw ValidationException::withMessages([
                        'devolucion' => [
                            "Stock insuficiente para anular devolucion de {$producto->nombre}.",
                        ],
                    ]);
                }

                $movimientosDevolucion = InventarioMovimiento::query()
                    ->where('tipo', 'devolucion_venta')
                    ->where('referencia', 'DEV-'.$devolucion->id)
                    ->where('venta_detalle_id', $detalle->venta_detalle_id)
                    ->whereNotNull('lote_id')
                    ->orderBy('id')
                    ->get(['lote_id', 'cantidad', 'costo_unitario']);

                $devolucionTrazada = (float) $movimientosDevolucion->sum(fn ($mov) => abs((float) $mov->cantidad));
                $usarTrazabilidadLote = $movimientosDevolucion->isNotEmpty() && abs($devolucionTrazada - (float) $cantidad) <= 0.0001;

                if ($usarTrazabilidadLote) {
                    $stockCursor = (float) $stockAnterior;

                    foreach ($movimientosDevolucion as $movDevolucion) {
                        $cantidadRevertir = toMoney(abs((float) $movDevolucion->cantidad), 4);
                        if ($cantidadRevertir <= 0) {
                            continue;
                        }

                        $lote = InventarioLote::query()->lockForUpdate()->find($movDevolucion->lote_id);
                        if (! $lote) {
                            throw ValidationException::withMessages([
                                'devolucion' => ['No se encontro el lote original de la devolucion para anular.'],
                            ]);
                        }

                        $disponible = (float) $lote->cantidad_disponible;
                        if ($disponible + 0.0001 < $cantidadRevertir) {
                            throw ValidationException::withMessages([
                                'devolucion' => [
                                    "No existen lotes suficientes para anular devolucion de {$producto->nombre}.",
                                ],
                            ]);
                        }

                        $lote->update([
                            'cantidad_disponible' => toMoney($disponible - $cantidadRevertir, 4),
                        ]);

                        $stockDespues = toMoney($stockCursor - $cantidadRevertir, 4);

                        InventarioMovimiento::query()->create([
                            'producto_id' => $producto->id,
                            'venta_id' => $devolucion->venta_id,
                            'venta_detalle_id' => $detalle->venta_detalle_id,
                            'lote_id' => (int) $movDevolucion->lote_id,
                            'tipo' => 'anulacion_devolucion',
                            'cantidad' => toMoney(-1 * $cantidadRevertir, 4),
                            'stock_anterior' => $stockCursor,
                            'stock_nuevo' => $stockDespues,
                            'costo_unitario' => toMoney((float) $movDevolucion->costo_unitario, 4),
                            'referencia' => 'DEV-'.$devolucion->id,
                            'nota' => 'Anulacion de devolucion',
                            'add_user' => $userId,
                        ]);

                        $stockCursor = (float) $stockDespues;
                    }

                    $stockNuevo = toMoney($stockCursor, 4);
                } else {
                    $restante = $cantidad;
                    $lotes = InventarioLote::query()
                        ->where('producto_id', $producto->id)
                        ->where('cantidad_disponible', '>', 0)
                        ->orderBy('fecha_entrada')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($lotes as $lote) {
                        if ($restante <= 0) {
                            break;
                        }

                        $disponible = (float) $lote->cantidad_disponible;
                        if ($disponible <= 0) {
                            continue;
                        }

                        $usar = min($restante, $disponible);
                        $lote->update([
                            'cantidad_disponible' => toMoney($disponible - $usar, 4),
                        ]);

                        $restante = toMoney($restante - $usar, 4);
                    }

                    if ($restante > 0.0001) {
                        throw ValidationException::withMessages([
                            'devolucion' => [
                                "No existen lotes suficientes para anular devolucion de {$producto->nombre}.",
                            ],
                        ]);
                    }

                    $stockNuevo = toMoney($stockAnterior - $cantidad, 4);
                    InventarioMovimiento::query()->create([
                        'producto_id' => $producto->id,
                        'venta_id' => $devolucion->venta_id,
                        'venta_detalle_id' => $detalle->venta_detalle_id,
                        'tipo' => 'anulacion_devolucion',
                        'cantidad' => toMoney(-1 * $cantidad, 4),
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'costo_unitario' => $producto->costo_promedio,
                        'referencia' => 'DEV-'.$devolucion->id,
                        'nota' => 'Anulacion de devolucion',
                        'add_user' => $userId,
                    ]);
                }

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'mod_user' => $userId,
                ]);
            }

            $devolucion->update([
                'estado' => 'anulada',
            ]);
        });

        return response()->json([
            'message' => 'Devolucion anulada correctamente.',
            'data' => $devolucion->fresh([
                'venta:id,numero,fecha_venta',
                'usuario:id,name',
                'detalles.producto:id,nombre',
            ]),
        ]);
    }

    public function ticket(Request $request, Devolucion $devolucion): Response
    {
        if ((int) $devolucion->usuario_id !== (int) $request->user()->id) {
            abort(403, 'No autorizado para ver este ticket.');
        }

        $devolucion->loadMissing([
            'venta:id,numero,fecha_venta',
            'usuario:id,name,username',
            'detalles.producto:id,nombre',
        ]);

        $lineas = max(12, $devolucion->detalles->count() + 12);
        $altoPuntos = max(430, (int) round($lineas * 22));

        $pdf = Pdf::loadView('tickets.devolucion', [
            'devolucion' => $devolucion,
            'empresa' => [
                'nombre' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
                'telefono' => Configuracion::valor('telefono_empresa', null),
                'direccion' => Configuracion::valor('direccion_empresa', null),
            ],
        ])->setPaper([0, 0, 226.77, $altoPuntos], 'portrait');

        return $pdf->stream('ticket-devolucion-'.$devolucion->id.'.pdf');
    }
}
