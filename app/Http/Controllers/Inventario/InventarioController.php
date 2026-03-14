<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function existencias(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['categoria:id,nombre', 'proveedor:id,nombre'])
            ->orderBy('nombre');

        if ($request->boolean('solo_bajo_stock')) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%");
            });
        }

        $productos = $query->get([
            'id',
            'categoria_id',
            'proveedor_id',
            'nombre',
            'codigo',
            'codigo_barra',
            'stock_actual',
            'stock_minimo',
            'costo_promedio',
            'precio_venta',
            'unidad_medida',
            'activo',
            'updated_at',
        ]);

        return response()->json([
            'data' => $productos,
        ]);
    }

    public function movimientos(Request $request): JsonResponse
    {
        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre,codigo',
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
}
