<?php

namespace App\Http\Middleware;

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

        return collect($this->permissionsByRequest($request))->unique()->values();
    }

    private function permissionsByRequest(Request $request): array
    {
        $path = preg_replace('/^api\//', '', $request->path()) ?? $request->path();

        if (preg_match('/^dashboard\//', $path)) {
            return ['dashboard'];
        }

        if (preg_match('/^configuracion\/usuarios\//', $path)) {
            return ['users'];
        }

        if (preg_match('/^configuracion\/(roles\/|permissions\/)/', $path)) {
            return ['roles'];
        }

        if (preg_match('/^configuracion\/configuraciones\//', $path)) {
            return ['configuraciones'];
        }

        if (preg_match('/^catalogos\/categorias\//', $path)) {
            return ['categorias'];
        }

        if (preg_match('/^catalogos\/productos\//', $path)) {
            return ['productos'];
        }

        if (preg_match('/^catalogos\/proveedores\//', $path)) {
            return ['proveedores'];
        }

        if (preg_match('/^catalogos\/clientes\//', $path)) {
            return ['cliente'];
        }

        if (preg_match('/^compras\//', $path)) {
            return ['compras'];
        }

        if (preg_match('/^ventas\//', $path)) {
            return ['ventas'];
        }

        if (preg_match('/^inventario\//', $path)) {
            return ['inventario'];
        }

        if (preg_match('/^gastos\//', $path)) {
            return ['gastos'];
        }

        if (preg_match('/^caja\//', $path)) {
            return [];
        }

        if (preg_match('/^reportes\//', $path)) {
            return ['reportes'];
        }

        if (preg_match('/^configuraciones\//', $path) || preg_match('/^auth\//', $path)) {
            return [];
        }

        abort(404, 'Ruta no existe en permisos.');
    }
}