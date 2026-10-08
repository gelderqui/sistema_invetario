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
        $this->normalizarNit($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:clientes,nombre'],
            'nit' => $this->nitRules(),
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
        $this->normalizarNit($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('clientes', 'nombre')->ignore($cliente->id)],
            'nit' => $this->nitRules($cliente),
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
        if ($cliente->ventas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar este cliente porque tiene ventas registradas. Solo puede desactivarlo.',
            ], 422);
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    private function normalizarNit(Request $request): void
    {
        $nit = strtoupper(trim((string) $request->input('nit', '')));
        $request->merge(['nit' => $nit === '' ? null : $nit]);
    }

    private function nitRules(?Cliente $cliente = null): array
    {
        $rules = ['nullable', 'string', 'max:40'];

        if (request('nit') !== 'CF') {
            $unique = Rule::unique('clientes', 'nit');
            if ($cliente) $unique->ignore($cliente->id);
            $rules[] = $unique;
        }

        return $rules;
    }
}
