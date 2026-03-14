<?php

use App\Http\Controllers\Admin\PermissionCatalogController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Catalogos\CategoriaController;
use App\Http\Controllers\Catalogos\ClienteController;
use App\Http\Controllers\Catalogos\ProveedorController;
use App\Http\Controllers\Catalogos\ProductoController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\Inventario\InventarioController;
use Illuminate\Support\Facades\Route;

// API endpoints
Route::prefix('api')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::middleware('guest')->post('/login', [AuthController::class, 'store']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'show']);
            Route::post('/logout', [AuthController::class, 'destroy']);
            Route::put('/password', [AuthController::class, 'updatePassword'])->middleware('ajax');
        });
    });

    Route::middleware('guest')->get('/configuraciones/get/login', [ConfiguracionController::class, 'login']);

    Route::middleware(['auth:sanctum', 'permission', 'ajax'])->group(function (): void {
        Route::get('/configuraciones/get/publicas', [ConfiguracionController::class, 'publicas']);

        Route::get('/dashboard/get', function () {
            return response()->json([
                'message' => 'Dashboard API is ready.',
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Route::prefix('admin')->group(function (): void {
            Route::prefix('users')->group(function (): void {
                Route::get('/get', [UserManagementController::class, 'index']);
                Route::post('/store', [UserManagementController::class, 'store']);
                Route::put('/update/{user}', [UserManagementController::class, 'update']);
                Route::get('/get/catalogs', [UserManagementController::class, 'catalogs']);
            });

            Route::prefix('roles')->group(function (): void {
                Route::get('/get', [RoleManagementController::class, 'index']);
                Route::post('/store', [RoleManagementController::class, 'store']);
                Route::put('/update/{role}', [RoleManagementController::class, 'update']);
            });

            Route::get('/permissions/get', [PermissionCatalogController::class, 'index']);
        });

        Route::prefix('catalogos')->group(function (): void {
            Route::prefix('categorias')->group(function (): void {
                Route::get('/get', [CategoriaController::class, 'index']);
                Route::post('/store', [CategoriaController::class, 'store']);
                Route::put('/update/{categoria}', [CategoriaController::class, 'update']);
                Route::patch('/toggle/{categoria}', [CategoriaController::class, 'toggle']);
                Route::delete('/destroy/{categoria}', [CategoriaController::class, 'destroy']);
            });

            Route::prefix('productos')->group(function (): void {
                Route::get('/get', [ProductoController::class, 'index']);
                Route::post('/store', [ProductoController::class, 'store']);
                Route::put('/update/{producto}', [ProductoController::class, 'update']);
                Route::patch('/toggle/{producto}', [ProductoController::class, 'toggle']);
                Route::delete('/destroy/{producto}', [ProductoController::class, 'destroy']);
            });

            Route::prefix('proveedores')->group(function (): void {
                Route::get('/get', [ProveedorController::class, 'index']);
                Route::post('/store', [ProveedorController::class, 'store']);
                Route::put('/update/{proveedor}', [ProveedorController::class, 'update']);
                Route::patch('/toggle/{proveedor}', [ProveedorController::class, 'toggle']);
                Route::delete('/destroy/{proveedor}', [ProveedorController::class, 'destroy']);
            });

            Route::prefix('clientes')->group(function (): void {
                Route::get('/get', [ClienteController::class, 'index']);
                Route::post('/store', [ClienteController::class, 'store']);
                Route::put('/update/{cliente}', [ClienteController::class, 'update']);
                Route::patch('/toggle/{cliente}', [ClienteController::class, 'toggle']);
                Route::delete('/destroy/{cliente}', [ClienteController::class, 'destroy']);
            });
        });

        Route::prefix('compras')->group(function (): void {
            Route::get('/get', [CompraController::class, 'index']);
            Route::get('/get/catalogs', [CompraController::class, 'catalogs']);
            Route::post('/store', [CompraController::class, 'store']);
        });

        Route::prefix('inventario')->group(function (): void {
            Route::get('/existencias/get', [InventarioController::class, 'existencias']);
            Route::get('/movimientos/get', [InventarioController::class, 'movimientos']);
        });
    });
});

// SPA entry point
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|sanctum).*$');
