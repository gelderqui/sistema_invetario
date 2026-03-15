<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\CompraDetalle;
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
            ]);

        return response()->json([
            'data' => [
                'categorias'          => $categorias,
                'proveedores'         => $proveedores,
                'proveedor_general_id' => $proveedorGeneral,
                'productos'           => $productos,
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
            'items.*.nota' => ['nullable', 'string', 'max:255'],
        ]);

        $proveedorActivo = Proveedor::query()
            ->whereKey($validated['proveedor_id'])
            ->where('activo', true)
            ->exists();

        if (! $proveedorActivo) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor seleccionado esta inactivo y no puede usarse en compras.',
            ]);
        }

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
                'estado' => 'activo',
                'total' => 0,
                'observaciones' => $validated['observaciones'] ?? null,
                'add_user' => getUserId(),
            ]);

            $total = 0.0;
            $alerts = [];

            foreach ($validated['items'] as $item) {
                $producto = Producto::query()->with('unidadMedida:id,abreviatura')->lockForUpdate()->findOrFail($item['producto_id']);

                if (! $producto->activo) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto {$producto->nombre} esta inactivo y no puede usarse en compras."],
                    ]);
                }

                $cantidad = toMoney($item['cantidad'], 4);
                $unidadMedidaSnap = $producto->unidadMedida?->abreviatura ?? 'und';
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
                    'add_user' => getUserId(),
                ]);

                $producto->update([
                    'proveedor_id'   => $compra->proveedor_id,
                    'costo_promedio' => $costoPromedioNuevo,
                    'stock_actual'   => $stockNuevo,
                    'precio_venta'   => $precioVentaAplicado,
                    'mod_user'       => getUserId(),
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
        });

        return response()->json([
            'message' => 'Compra anulada correctamente.',
            'data' => $compra->fresh(['proveedor:id,nombre'])->loadCount('detalles'),
        ]);
    }
}
