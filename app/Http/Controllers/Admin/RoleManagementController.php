<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with(['permissions:id,name,code,module'])
            ->withCount('users')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'description',
                'activo',
                'is_system',
                'created_at',
            ])
            ->sortBy(function (Role $role): string {
                $priority = match ($role->code) {
                    'admin' => '1',
                    'operador' => '2',
                    'cajero' => '3',
                    default => '4',
                };

                return $priority.'-'.mb_strtolower((string) $role->name);
            })
            ->values();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:roles,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'code' => strtolower($validated['code']),
            'description' => $validated['description'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? true),
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $role->load('permissions:id,name,code,module');

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => $role,
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->code === 'admin' && ! $request->boolean('activo')) {
            return response()->json([
                'message' => 'El rol admin no se puede desactivar.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('roles', 'code')->ignore($role->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        if ($role->code === 'admin' && strtolower((string) $validated['code']) !== 'admin') {
            return response()->json([
                'message' => 'El codigo del rol admin no se puede modificar.',
            ], 422);
        }

        $role->update([
            'name' => $validated['name'],
            'code' => strtolower($validated['code']),
            'description' => $validated['description'] ?? null,
            'activo' => (bool) $validated['activo'],
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $role->load('permissions:id,name,code,module');

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->code === 'admin') {
            return response()->json([
                'message' => 'El rol admin no se puede eliminar.',
            ], 422);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque tiene usuarios asignados.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rol eliminado correctamente.',
        ]);
    }
}
