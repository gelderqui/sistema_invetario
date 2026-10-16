<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->load('role');
        }

        if ($user && $user->activo && $user->role?->activo) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new JsonResponse([
            'message' => 'Tu sesión fue cerrada porque el usuario o su rol está inactivo.',
            'code' => 'ACCOUNT_OR_ROLE_INACTIVE',
            'force_logout' => true,
        ], 401);
    }
}
