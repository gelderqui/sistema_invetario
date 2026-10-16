<?php

require_once __DIR__.'/../app/Support/helpers.php';

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAjaxRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'active.account' => EnsureActiveAccount::class,
            'ajax'       => EnsureAjaxRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            return null;
        });
    })->create();
