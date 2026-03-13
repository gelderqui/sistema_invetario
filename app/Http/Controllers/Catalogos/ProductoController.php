<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(): JsonResponse
    {
        $productos = Producto::query()
            ->with('categoria:id,nombre')
            ->orderBy('nombre')
            ->get([
                'id',
                'categoria_id',
                'nombre',
                'codigo',
                'codigo_barra',
                'detalle',
                'palabras_clave',
                'activo',
                'created_at',
            ]);

        return response()->json([
            'data' => $productos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id'  => ['nullable', Rule::exists('categorias', 'id')],
            'nombre'        => ['required', 'string', 'max:255'],
            'codigo'        => ['required', 'string', 'max:100', 'unique:productos,codigo'],
            'codigo_barra'  => ['nullable', 'string', 'max:100', 'unique:productos,codigo_barra'],
            'detalle'       => ['nullable', 'string'],
            'palabras_clave'=> ['nullable', 'string', 'max:500'],
            'activo'        => ['sometimes', 'boolean'],
        ]);

        $producto = Producto::query()->create([
            ...$validated,
            'activo'   => (bool) ($validated['activo'] ?? true),
            'add_user' => $request->user()->id,
        ]);

        $producto->load('categoria:id,nombre');

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data'    => $producto,
        ], 201);
    }

    public function update(Request $request, Producto $producto): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id'  => ['nullable', Rule::exists('categorias', 'id')],
            'nombre'        => ['required', 'string', 'max:255'],
            'codigo'        => ['required', 'string', 'max:100', Rule::unique('productos', 'codigo')->ignore($producto->id)],
            'codigo_barra'  => ['nullable', 'string', 'max:100', Rule::unique('productos', 'codigo_barra')->ignore($producto->id)],
            'detalle'       => ['nullable', 'string'],
            'palabras_clave'=> ['nullable', 'string', 'max:500'],
            'activo'        => ['required', 'boolean'],
        ]);

        $producto->update([
            ...$validated,
            'activo'   => (bool) $validated['activo'],
            'mod_user' => $request->user()->id,
        ]);

        $producto->load('categoria:id,nombre');

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data'    => $producto,
        ]);
    }

    public function toggle(Request $request, Producto $producto): JsonResponse
    {
        $producto->update([
            'activo'   => ! $producto->activo,
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data'    => $producto,
        ]);
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
