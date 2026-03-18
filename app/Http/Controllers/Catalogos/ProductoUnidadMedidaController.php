<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoUnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoUnidadMedidaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductoUnidadMedida::query()
            ->withCount('productos')
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'abreviatura', 'activo', 'created_at']);

        if ($request->boolean('solo_activas')) {
            $query->where('activo', true);
        }

        $medidas = $query->get();

        return response()->json([
            'data' => $medidas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:80', 'unique:productos_unidad_medida,nombre'],
            'abreviatura' => ['required', 'string', 'max:10', 'unique:productos_unidad_medida,abreviatura'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $medida = ProductoUnidadMedida::query()->create([
            'nombre' => $validated['nombre'],
            'abreviatura' => $validated['abreviatura'],
            'activo' => (bool) ($validated['activo'] ?? true),
        ]);

        return response()->json([
            'message' => 'Unidad de medida creada correctamente.',
            'data' => $medida,
        ], 201);
    }

    public function update(Request $request, ProductoUnidadMedida $medida): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('productos_unidad_medida', 'nombre')->ignore($medida->id)],
            'abreviatura' => ['required', 'string', 'max:10', Rule::unique('productos_unidad_medida', 'abreviatura')->ignore($medida->id)],
            'activo' => ['required', 'boolean'],
        ]);

        $medida->update([
            'nombre' => $validated['nombre'],
            'abreviatura' => $validated['abreviatura'],
            'activo' => (bool) $validated['activo'],
        ]);

        return response()->json([
            'message' => 'Unidad de medida actualizada correctamente.',
            'data' => $medida,
        ]);
    }

    public function toggle(ProductoUnidadMedida $medida): JsonResponse
    {
        $nuevoEstado = ! $medida->activo;

        if (! $nuevoEstado) {
            $productosActivos = Producto::query()
                ->where('unidad_medida_id', $medida->id)
                ->where('activo', true)
                ->exists();

            if ($productosActivos) {
                return response()->json([
                    'message' => 'No se puede desactivar esta unidad porque tiene productos activos asociados.',
                ], 422);
            }
        }

        $medida->update([
            'activo' => $nuevoEstado,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $medida,
        ]);
    }

    public function destroy(ProductoUnidadMedida $medida): JsonResponse
    {
        if ($medida->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar esta unidad porque esta asociada a productos.',
            ], 422);
        }

        $medida->delete();

        return response()->json([
            'message' => 'Unidad de medida eliminada correctamente.',
        ]);
    }
}
