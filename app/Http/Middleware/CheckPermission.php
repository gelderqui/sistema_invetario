<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $user->loadMissing('role.permissions');

        if (! $user->activo || ! $user->role) {
            abort(403, 'Usuario inactivo o sin rol asignado.');
        }

        // El rol admin siempre tiene acceso total.
        if ($user->role->code === 'admin') {
            return $next($request);
        }

        $requiredPermissions = $this->resolveRequiredPermissions($request, $permissions);

        // Si no hay permiso requerido para esta ruta, se permite continuar.
        if ($requiredPermissions->isEmpty()) {
            return $next($request);
        }

        if (! $user->hasPermission($requiredPermissions->all())) {
            abort(403, 'You do not have the required permission.');
        }

        return $next($request);
    }

    private function resolveRequiredPermissions(Request $request, array $permissions): Collection
    {
        $parsed = collect($permissions)
            ->flatMap(fn (string $value) => preg_split('/[|,]/', $value) ?: [])
            ->map(fn (string $value) => trim($value))
            ->filter();

        if ($parsed->isNotEmpty()) {
            return $parsed->unique()->values();
        }

        $resolved = $this->permissionFromRoute($request);

        if ($resolved === null) {
            abort(404, 'Ruta no existe en permisos.');
        }

        return collect([$resolved]);
    }

    private function permissionFromRoute(Request $request): ?string
    {
        $rawPath = preg_replace('/^api\//', '', $request->path()) ?? $request->path();
        $path = trim($rawPath, '/');

        if ($path === '' || preg_match('/^auth\//', $path)) {
            return null;
        }

        if ($path === 'dashboard/get') {
            return 'dashboard';
        }

        $segments = explode('/', $path);
        $candidates = [];

        for ($i = count($segments); $i > 0; $i--) {
            $candidates[] = '/'.implode('/', array_slice($segments, 0, $i));
        }

        foreach ($candidates as $candidate) {
            $permissionCode = Permission::query()
                ->where('activo', true)
                ->where('ruta', $candidate)
                ->value('code');

            if ($permissionCode) {
                return (string) $permissionCode;
            }
        }

        return null;
    }
}