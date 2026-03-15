<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(): JsonResponse
    {
        $ventas = Venta::query()
            ->with(['cliente:id,nombre'])
            ->withCount('detalles')
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->get([
                'id',
                'numero',
                'cliente_id',
                'fecha_venta',
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
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
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

                $cantidad = toMoney($item['cantidad'], 4);
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
}
