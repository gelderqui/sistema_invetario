<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(): JsonResponse
    {
        $clientes = Cliente::query()
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'nit',
                'email',
                'telefono',
                'direccion',
                'activo',
                'created_at',
            ]);

        return response()->json([
            'data' => $clientes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:clientes,nombre'],
            'nit' => ['nullable', 'string', 'max:40', 'unique:clientes,nit'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cliente = Cliente::query()->create([
            ...$validated,
            'nit' => $validated['nit'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? true),
            'add_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'data' => $cliente,
        ], 201);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('clientes', 'nombre')->ignore($cliente->id)],
            'nit' => ['nullable', 'string', 'max:40', Rule::unique('clientes', 'nit')->ignore($cliente->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        $cliente->update([
            ...$validated,
            'nit' => $validated['nit'] ?? null,
            'activo' => (bool) $validated['activo'],
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'data' => $cliente,
        ]);
    }

    public function toggle(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente->update([
            'activo' => ! $cliente->activo,
            'mod_user' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $cliente,
        ]);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }
}
