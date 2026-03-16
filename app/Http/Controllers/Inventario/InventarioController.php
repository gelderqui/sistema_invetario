<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function existencias(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['categoria:id,nombre', 'unidadMedida:id,nombre,abreviatura'])
            ->orderBy('nombre');

        if ($request->boolean('solo_bajo_stock')) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }

        $productos = $query->get([
            'id',
            'categoria_id',
            'nombre',
            'codigo_barra',
            'stock_actual',
            'stock_minimo',
            'costo_promedio',
            'precio_venta_promedio',
            'costo_ultimo',
            'precio_venta',
            'unidad_medida_id',
            'activo',
            'updated_at',
        ])->map(function (Producto $producto): array {
            return [
                'id' => $producto->id,
                'categoria_id' => $producto->categoria_id,
                'categoria' => $producto->categoria,
                'nombre' => $producto->nombre,
                'codigo_barra' => $producto->codigo_barra,
                'stock_actual' => $producto->stock_actual,
                'stock_minimo' => $producto->stock_minimo,
                'costo_promedio' => $producto->costo_promedio,
                'precio_venta_promedio' => $producto->precio_venta_promedio,
                'costo_ultimo' => $producto->costo_ultimo,
                'precio_venta' => $producto->precio_venta,
                'unidad_medida_abreviatura' => $producto->unidadMedida?->abreviatura,
                'activo' => $producto->activo,
                'updated_at' => $producto->updated_at,
            ];
        });

        $categorias = Categoria::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json([
            'data' => $productos,
            'categorias' => $categorias,
        ]);
    }

    public function movimientos(Request $request): JsonResponse
    {
        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre',
                'compra:id,numero',
            ])
            ->orderByDesc('id');

        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->integer('producto_id'));
        }

        $movimientos = $query->limit(200)->get([
            'id',
            'producto_id',
            'compra_id',
            'tipo',
            'cantidad',
            'stock_anterior',
            'stock_nuevo',
            'costo_unitario',
            'referencia',
            'nota',
            'created_at',
        ]);

        return response()->json([
            'data' => $movimientos,
        ]);
    }

    public function alertas(Request $request): JsonResponse
    {
        $hoy = Carbon::today()->toDateString();

        $bajoStock = Producto::query()
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'stock_actual',
                'stock_minimo',
            ]);

        $porVencer = InventarioLote::query()
            ->join('productos', 'productos.id', '=', 'inventario_lotes.producto_id')
            ->where('productos.control_vencimiento', true)
            ->where('inventario_lotes.cantidad_disponible', '>', 0)
            ->whereNotNull('inventario_lotes.fecha_vencimiento')
            ->whereRaw('inventario_lotes.fecha_vencimiento >= ?', [$hoy])
            ->whereRaw('inventario_lotes.fecha_vencimiento <= DATE_ADD(?, INTERVAL productos.dias_alerta_vencimiento DAY)', [$hoy])
            ->orderBy('inventario_lotes.fecha_vencimiento')
            ->get([
                'inventario_lotes.id as lote_id',
                'inventario_lotes.producto_id',
                'productos.nombre as producto_nombre',
                'inventario_lotes.cantidad_disponible',
                'inventario_lotes.fecha_vencimiento',
                'productos.dias_alerta_vencimiento',
            ]);

        $vencidos = InventarioLote::query()
            ->join('productos', 'productos.id', '=', 'inventario_lotes.producto_id')
            ->where('productos.control_vencimiento', true)
            ->where('inventario_lotes.cantidad_disponible', '>', 0)
            ->whereNotNull('inventario_lotes.fecha_vencimiento')
            ->where('inventario_lotes.fecha_vencimiento', '<', $hoy)
            ->orderBy('inventario_lotes.fecha_vencimiento')
            ->get([
                'inventario_lotes.id as lote_id',
                'inventario_lotes.producto_id',
                'productos.nombre as producto_nombre',
                'inventario_lotes.cantidad_disponible',
                'inventario_lotes.fecha_vencimiento',
            ]);

        return response()->json([
            'data' => [
                'bajo_stock' => $bajoStock,
                'por_vencer' => $porVencer,
                'vencidos' => $vencidos,
            ],
        ]);
    }
}
