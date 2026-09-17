<?php

namespace App\Http\Controllers\Activos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Activo::query()->with('usuario:id,name')->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $activo = Activo::query()->create([...$this->validated($request), 'usuario_id' => $request->user()->id]);

        return response()->json(['message' => 'Activo creado correctamente.', 'data' => $activo->load('usuario:id,name')], 201);
    }

    public function update(Request $request, Activo $activo): JsonResponse
    {
        $activo->update($this->validated($request, $activo));

        return response()->json(['message' => 'Activo actualizado correctamente.', 'data' => $activo->fresh()->load('usuario:id,name')]);
    }

    public function toggle(Activo $activo): JsonResponse
    {
        $activo->update(['activo' => ! $activo->activo]);

        return response()->json(['message' => 'Estado actualizado correctamente.', 'data' => $activo->fresh()->load('usuario:id,name')]);
    }

    private function validated(Request $request, ?Activo $activo = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('activos', 'codigo')->ignore($activo?->id)],
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['required', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'fecha_compra' => ['nullable', 'date'],
            'costo_compra' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(['activo', 'mantenimiento', 'danado', 'baja'])],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['required', 'boolean'],
        ]);
    }
}
