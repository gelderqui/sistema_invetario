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
        $validated = $request->validate([
            'solo_bajo_stock' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
        ]);

        $query = Producto::query()
            ->with(['categoria:id,nombre', 'unidadMedida:id,nombre,abreviatura'])
            ->orderBy('nombre');

        if ((bool) ($validated['solo_bajo_stock'] ?? false)) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['categoria_id'])) {
            $query->where('categoria_id', (int) $validated['categoria_id']);
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
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
        ]);

        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre',
                'compra:id,numero',
            ])
            ->orderByDesc('id');

        if (! empty($validated['fecha_desde'])) {
            $query->whereDate('created_at', '>=', (string) $validated['fecha_desde']);
        }

        if (! empty($validated['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', (string) $validated['fecha_hasta']);
        }

        if (! empty($validated['categoria_id'])) {
            $categoriaId = (int) $validated['categoria_id'];
            $query->whereHas('producto', fn ($q) => $q->where('categoria_id', $categoriaId));
        }

        if (! empty($validated['producto_id'])) {
            $query->where('producto_id', (int) $validated['producto_id']);
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

        $categorias = Categoria::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $productosQuery = Producto::query()
            ->where('activo', true)
            ->whereHas('movimientosInventario')
            ->orderBy('nombre');

        if (! empty($validated['categoria_id'])) {
            $productosQuery->where('categoria_id', (int) $validated['categoria_id']);
        }

        $productos = $productosQuery->get(['id', 'nombre']);

        return response()->json([
            'data' => $movimientos,
            'categorias' => $categorias,
            'productos' => $productos,
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

        $lotesActivosConVencimiento = InventarioLote::query()
            ->with(['producto:id,nombre,control_vencimiento,dias_alerta_vencimiento'])
            ->where('cantidad_disponible', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->whereHas('producto', fn ($q) => $q->where('control_vencimiento', true))
            ->orderBy('fecha_vencimiento')
            ->get(['id', 'producto_id', 'cantidad_disponible', 'fecha_vencimiento']);

        $porVencer = $lotesActivosConVencimiento
            ->filter(function (InventarioLote $lote) use ($hoy): bool {
                if (! $lote->fecha_vencimiento || ! $lote->producto) {
                    return false;
                }

                $fechaVencimiento = Carbon::parse((string) $lote->fecha_vencimiento)->toDateString();
                if ($fechaVencimiento < $hoy) {
                    return false;
                }

                $diasRestantes = Carbon::parse((string) $hoy)->diffInDays(Carbon::parse((string) $fechaVencimiento));

                return $diasRestantes <= (int) $lote->producto->dias_alerta_vencimiento;
            })
            ->values()
            ->map(fn (InventarioLote $lote): array => [
                'lote_id' => $lote->id,
                'producto_id' => $lote->producto_id,
                'producto_nombre' => $lote->producto?->nombre,
                'cantidad_disponible' => $lote->cantidad_disponible,
                'fecha_vencimiento' => $lote->fecha_vencimiento,
                'dias_alerta_vencimiento' => (int) ($lote->producto?->dias_alerta_vencimiento ?? 0),
            ]);

        $vencidos = $lotesActivosConVencimiento
            ->filter(function (InventarioLote $lote) use ($hoy): bool {
                if (! $lote->fecha_vencimiento) {
                    return false;
                }

                return Carbon::parse((string) $lote->fecha_vencimiento)->toDateString() < $hoy;
            })
            ->values()
            ->map(fn (InventarioLote $lote): array => [
                'lote_id' => $lote->id,
                'producto_id' => $lote->producto_id,
                'producto_nombre' => $lote->producto?->nombre,
                'cantidad_disponible' => $lote->cantidad_disponible,
                'fecha_vencimiento' => $lote->fecha_vencimiento,
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
