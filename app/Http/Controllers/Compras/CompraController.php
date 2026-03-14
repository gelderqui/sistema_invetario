<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
                'total',
                'observaciones',
                'created_at',
            ]);

        return response()->json([
            'data' => $compras,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $proveedores = Proveedor::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $productos = Producto::query()
            ->where('activo', true)
            ->with(['categoria:id,nombre'])
            ->orderBy('nombre')
            ->get([
                'id',
                'categoria_id',
                'nombre',
                'codigo_barra',
                'costo_promedio',
                'precio_venta',
                'stock_actual',
                'unidad_medida',
            ]);

        return response()->json([
            'data' => [
                'proveedores' => $proveedores,
                'productos' => $productos,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')],
            'fecha_compra' => ['required', 'date'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_venta' => ['nullable', 'numeric', 'gte:0'],
            'items.*.fecha_caducidad' => ['nullable', 'date'],
            'items.*.peso' => ['nullable', 'numeric', 'gt:0'],
            'items.*.nota' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $numeroCompra = sprintf(
                'CMP-%s-%03d',
                now()->format('YmdHis'),
                random_int(1, 999)
            );

            $compra = Compra::query()->create([
                'numero' => $numeroCompra,
                'proveedor_id' => $validated['proveedor_id'],
                'fecha_compra' => $validated['fecha_compra'],
                'estado' => 'registrada',
                'total' => 0,
                'observaciones' => $validated['observaciones'] ?? null,
                'add_user' => getUserId(),
            ]);

            $total = 0.0;
            $alerts = [];

            foreach ($validated['items'] as $item) {
                $producto = Producto::query()->lockForUpdate()->findOrFail($item['producto_id']);

                $cantidad = toMoney($item['cantidad'], 4);
                $costoUnitario = toMoney($item['costo_unitario'], 4);
                $subtotal = toMoney($cantidad * $costoUnitario, 4);

                $stockAnterior = (float) $producto->stock_actual;
                $stockNuevo = toMoney($stockAnterior + $cantidad, 4);
                $costoAnterior = (float) $producto->costo_promedio;
                $costoPromedioNuevo = weightedAverageCost($stockAnterior, $costoAnterior, $cantidad, $costoUnitario);

                $ratioVenta = $costoAnterior > 0
                    ? ((float) $producto->precio_venta / $costoAnterior)
                    : 1.35;
                $ratioVenta = $ratioVenta <= 0 ? 1.35 : $ratioVenta;

                $precioVentaSugerido = toMoney($costoPromedioNuevo * $ratioVenta, 4);
                $precioVentaAplicado = toMoney($item['precio_venta'] ?? $precioVentaSugerido, 4);

                if (abs($precioVentaAplicado - $precioVentaSugerido) > 0.0001) {
                    $alerts[] = sprintf(
                        'El precio de venta de %s difiere del sugerido (%.2f vs %.2f).',
                        $producto->nombre,
                        $precioVentaAplicado,
                        $precioVentaSugerido
                    );
                }

                $detalle = CompraDetalle::query()->create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                    'precio_venta_sugerido' => $precioVentaSugerido,
                    'precio_venta_aplicado' => $precioVentaAplicado,
                    'fecha_caducidad' => $item['fecha_caducidad'] ?? null,
                    'peso' => $item['peso'] ?? null,
                    'cantidad_disponible' => $cantidad,
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
                    'add_user' => getUserId(),
                ]);

                $producto->update([
                    'proveedor_id' => $compra->proveedor_id,
                    'costo_promedio' => $costoPromedioNuevo,
                    'stock_actual' => $stockNuevo,
                    'precio_venta' => $precioVentaAplicado,
                    'mod_user' => getUserId(),
                ]);

                $total += $subtotal;
            }

            $compra->update([
                'total' => toMoney($total, 4),
                'mod_user' => getUserId(),
            ]);

            return [
                'compra' => $compra->load(['proveedor:id,nombre', 'detalles.producto:id,nombre']),
                'alerts' => $alerts,
            ];
        });

        return response()->json([
            'message' => 'Compra registrada correctamente.',
            'data' => $result['compra'],
            'alerts' => $result['alerts'],
        ], 201);
    }
}
