<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAjaxRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->ajax()) {
            return response()->json(['message' => 'Acceso denegado. Solo se permiten peticiones AJAX.'], 403);
        }

        return $next($request);
    }
}
