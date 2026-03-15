<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private function isProtectedAdmin(User $user): bool
    {
        return strtolower($user->username) === 'admin' || strtolower($user->email) === 'admin@admin.local';
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->withTrashed()
            ->with(['role:id,name,code'])
            ->orderBy('name')
            ->get([
                'id',
                'username',
                'name',
                'email',
                'telefono',
                'activo',
                'role_id',
                'deleted_at',
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
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        $user = User::query()->create([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'password' => $validated['password'],
            'role_id' => $validated['role_id'] ?? null,
            'activo' => (bool) ($validated['activo'] ?? true),
        ]);

        $user->load('role:id,name,code');

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->trashed()) {
            return response()->json([
                'message' => 'No se puede editar un usuario eliminado.',
            ], 422);
        }

        if ($this->isProtectedAdmin($user) && ! $request->boolean('activo')) {
            return response()->json([
                'message' => 'El usuario admin no se puede desactivar.',
            ], 422);
        }

        $validated = $request->validate([
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
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);

        if ($this->isProtectedAdmin($user) && isset($validated['role_id']) && (int) $validated['role_id'] !== (int) $user->role_id) {
            return response()->json([
                'message' => 'El usuario admin principal no puede cambiar de rol.',
            ], 422);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'activo' => (bool) $validated['activo'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        if (! empty($validated['password'])) {
            // Invalidate remember-me cookies for this specific user.
            $user->forceFill([
                'remember_token' => Str::random(60),
            ])->save();

            // Invalidate only this user's active sessions when using database session driver.
            $sessionDriver = (string) config('session.driver');
            $sessionTable = (string) config('session.table', 'sessions');

            if ($sessionDriver === 'database' && Schema::hasTable($sessionTable)) {
                DB::table($sessionTable)
                    ->where('user_id', $user->id)
                    ->delete();
            }
        }

        $user->load('role:id,name,code');

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $user,
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($this->isProtectedAdmin($user)) {
            return response()->json([
                'message' => 'El usuario admin no se puede eliminar.',
            ], 422);
        }

        if ($user->trashed()) {
            return response()->json([
                'message' => 'El usuario ya se encuentra eliminado.',
            ], 422);
        }

        $user->update(['activo' => false]);
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
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
