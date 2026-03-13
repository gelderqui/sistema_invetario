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
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'description',
                'activo',
                'is_system',
                'created_at',
            ]);

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
}
