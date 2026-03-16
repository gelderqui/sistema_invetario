<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Configuracion;
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

    public function catalogs(): JsonResponse
    {
        $clientes = Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit']);

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
                'stock_actual',
                'unidad_medida_id',
            ]);

        return response()->json([
            'data' => [
                'clientes' => $clientes,
                'consumidor_final_id' => $consumidorFinalId,
                'productos' => $productos,
                'metodos_pago' => ['efectivo', 'tarjeta', 'transferencia', 'mixto'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')],
            'fecha_venta' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia', 'mixto'])],
            'descuento' => ['nullable', 'numeric', 'gte:0'],
            'monto_recibido' => ['nullable', 'numeric', 'gte:0'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
        ]);

        $result = DB::transaction(function () use ($validated) {
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
                $precio = toMoney($item['precio_unitario'], 4);
                $lineSubtotal = toMoney($cantidad * $precio, 4);
                $stockAnterior = (float) $producto->stock_actual;

                if ($stockAnterior + 0.0001 < $cantidad) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para {$producto->nombre}. Disponible: {$stockAnterior}"],
                    ]);
                }

                $remaining = $cantidad;
                $lotes = InventarioLote::query()
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

                InventarioMovimiento::query()->create([
                    'producto_id' => $producto->id,
                    'venta_id' => $venta->id,
                    'venta_detalle_id' => $detalle->id,
                    'tipo' => 'salida_venta',
                    'cantidad' => toMoney(-1 * $cantidad, 4),
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'costo_unitario' => $producto->costo_promedio,
                    'referencia' => $venta->numero,
                    'nota' => 'Venta registrada',
                    'add_user' => getUserId(),
                ]);

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'mod_user' => getUserId(),
                ]);

                $subtotal += $lineSubtotal;
            }

            $descuento = toMoney($venta->descuento, 4);
            $total = toMoney(max(0, $subtotal - $descuento), 4);

            $montoRecibido = $validated['monto_recibido'] ?? null;
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

            if ($venta->metodo_pago === 'efectivo' && getUserId()) {
                registrarMovimientoCajaAutomatico(
                    getUserId(),
                    'venta',
                    $total,
                    'Ingreso por venta '.$venta->numero,
                    $venta->fecha_venta?->toDateString(),
                    'venta',
                    $venta->id
                );
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
                $stockNuevo = toMoney($stockAnterior + $cantidad, 4);

                $producto->update([
                    'stock_actual' => $stockNuevo,
                    'mod_user' => $userId,
                ]);

                if ((bool) $producto->control_vencimiento) {
                    InventarioLote::query()->create([
                        'producto_id' => $producto->id,
                        'compra_detalle_id' => null,
                        'cantidad_inicial' => $cantidad,
                        'cantidad_disponible' => $cantidad,
                        'costo_unitario' => toMoney($producto->costo_promedio, 4),
                        'fecha_vencimiento' => null,
                        'fecha_entrada' => now()->toDateString(),
                    ]);
                }

                InventarioMovimiento::query()->create([
                    'producto_id' => $producto->id,
                    'venta_id' => $venta->id,
                    'venta_detalle_id' => $detalle->id,
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

            $venta->update([
                'estado' => 'anulada',
                'mod_user' => $userId,
            ]);

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

    public function ticket(Request $request, Venta $venta): Response
    {
        if ((int) $venta->add_user !== (int) $request->user()->id) {
            abort(403, 'No autorizado para ver este ticket.');
        }

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
