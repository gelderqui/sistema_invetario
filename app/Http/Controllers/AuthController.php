<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
            'activo' => true,
        ];

        if (! Auth::attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user()->load(['role.permissions']);

        return response()->json([
            'message' => 'Authenticated successfully.',
            'user' => new AuthenticatedUserResource($user),
        ]);
    }

    public function show(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource(
            $request->user()->load(['role.permissions'])
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ], [
            'password.confirmed' => 'La confirmacion de la contrasena no coincide.',
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contrasena actual es incorrecta.',
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        $sessionDriver = (string) config('session.driver');
        $sessionTable = (string) config('session.table', 'sessions');

        if ($sessionDriver === 'database' && Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json([
            'message' => 'Contrasena actualizada correctamente. Se cerrara sesion en todos los navegadores.',
            'force_logout' => true,
        ]);
    }
}
