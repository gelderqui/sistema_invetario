<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index(): JsonResponse
    {
        $proveedores = Proveedor::query()
            ->withCount(['compras', 'productos'])
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'contacto',
                'email',
                'telefono',
                'direccion',
                'activo',
                'created_at',
            ]);

        return response()->json([
            'data' => $proveedores,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:proveedores,nombre'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $proveedor = Proveedor::query()->create([
            ...$validated,
            'activo' => (bool) ($validated['activo'] ?? true),
            'add_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Proveedor creado correctamente.',
            'data' => $proveedor,
        ], 201);
    }

    public function update(Request $request, Proveedor $proveedor): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('proveedores', 'nombre')->ignore($proveedor->id)],
            'contacto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        $proveedor->update([
            ...$validated,
            'activo' => (bool) $validated['activo'],
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Proveedor actualizado correctamente.',
            'data' => $proveedor,
        ]);
    }

    public function toggle(Request $request, Proveedor $proveedor): JsonResponse
    {
        $proveedor->update([
            'activo' => ! $proveedor->activo,
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $proveedor,
        ]);
    }

    public function destroy(Proveedor $proveedor): JsonResponse
    {
        if ($proveedor->compras()->exists()) {
            return response()->json([
                'message' => 'No puede eliminar este proveedor porque tiene compras registradas.',
            ], 422);
        }

        if ($proveedor->productos()->exists()) {
            return response()->json([
                'message' => 'No puede eliminar este proveedor porque tiene productos asociados.',
            ], 422);
        }

        $proveedor->delete();

        return response()->json([
            'message' => 'Proveedor eliminado correctamente.',
        ]);
    }
}
