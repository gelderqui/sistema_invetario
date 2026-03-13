<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Categoria::query()
            ->withCount('productos')
            ->orderBy('nombre');

        if ($request->boolean('solo_activas')) {
            $query->where('activo', true);
        }

        return response()->json([
            'data' => $query->get(['id', 'nombre', 'descripcion', 'activo', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'      => ['required', 'string', 'max:255', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo'      => ['sometimes', 'boolean'],
        ]);

        $categoria = Categoria::query()->create([
            'nombre'      => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo'      => (bool) ($validated['activo'] ?? true),
            'add_user'    => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data'    => $categoria,
        ], 201);
    }

    public function update(Request $request, Categoria $categoria): JsonResponse
    {
        $validated = $request->validate([
            'nombre'      => ['required', 'string', 'max:255', Rule::unique('categorias', 'nombre')->ignore($categoria->id)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo'      => ['required', 'boolean'],
        ]);

        $categoria->update([
            'nombre'      => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo'      => (bool) $validated['activo'],
            'mod_user'    => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data'    => $categoria,
        ]);
    }

    public function toggle(Request $request, Categoria $categoria): JsonResponse
    {
        $categoria->update([
            'activo'   => ! $categoria->activo,
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data'    => $categoria,
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        if ($categoria->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la categoría tiene productos asignados.',
            ], 422);
        }

        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}
