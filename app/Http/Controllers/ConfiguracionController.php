<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function login(): JsonResponse
    {
        return response()->json([
            'nombre_empresa' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
        ]);
    }

    public function publicas(): JsonResponse
    {
        return response()->json([
            'nombre_empresa' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
            'tiempo_sesion' => (int) Configuracion::valor('tiempo_sesion', 1),
        ]);
    }

    public function index(): JsonResponse
    {
        $items = Configuracion::query()
            ->orderBy('codigo')
            ->get([
                'id',
                'codigo',
                'descripcion',
                'value',
                'activo',
                'created_at',
                'updated_at',
                'last_modified_by_user_id',
                'last_modified_by_user_name',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:configuraciones,codigo'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $item = Configuracion::query()->create([
            'codigo' => strtolower($validated['codigo']),
            'descripcion' => $validated['descripcion'] ?? null,
            'value' => $validated['value'],
            'activo' => (bool) ($validated['activo'] ?? true),
            'last_modified_by_user_id' => $user?->id,
            'last_modified_by_user_name' => $user?->name ?? $user?->username,
        ]);

        return response()->json([
            'message' => 'Configuracion creada correctamente.',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, Configuracion $configuracion): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string'],
        ]);

        $user = $request->user();

        $configuracion->update([
            'value' => $validated['value'],
            'last_modified_by_user_id' => $user?->id,
            'last_modified_by_user_name' => $user?->name ?? $user?->username,
        ]);

        return response()->json([
            'message' => 'Configuracion actualizada correctamente.',
            'data' => $configuracion,
        ]);
    }

    public function toggle(Configuracion $configuracion): JsonResponse
    {
        $configuracion->update([
            'activo' => ! $configuracion->activo,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $configuracion,
        ]);
    }

    public function destroy(Configuracion $configuracion): JsonResponse
    {
        $configuracion->delete();

        return response()->json([
            'message' => 'Configuracion eliminada correctamente.',
        ]);
    }
}
