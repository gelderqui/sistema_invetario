<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with(['roles:id,name,code'])
            ->orderBy('name')
            ->get([
                'id',
                'username',
                'name',
                'email',
                'telefono',
                'activo',
                'created_at',
            ]);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'activo' => ['sometimes', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        $user = User::query()->create([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'password' => $validated['password'],
            'activo' => (bool) ($validated['activo'] ?? true),
        ]);

        $user->roles()->sync($validated['role_ids'] ?? []);
        $user->load('roles:id,name,code');

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'activo' => ['required', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        $payload = [
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'activo' => (bool) $validated['activo'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);
        $user->roles()->sync($validated['role_ids'] ?? []);
        $user->load('roles:id,name,code');

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $user,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $roles = Role::query()
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
