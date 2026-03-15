<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Auth\SessionGuard;
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

        $remember = (bool) ($validated['remember'] ?? false);
        $guard = Auth::guard('web');

        if ($remember && $guard instanceof SessionGuard) {
            $diasSesion = max(1, (int) Configuracion::valor('tiempo_sesion', 1));
            $rememberDuration = max(1, $diasSesion * 24 * 60);

            $guard->setRememberDuration($rememberDuration);
        }

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user()->load(['role.permissions']);

        if (! $user->role || ! $user->role->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Tu usuario no puede iniciar sesion porque su rol esta inactivo o no asignado.',
            ]);
        }

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
