<?php

use App\Http\Controllers\Admin\PermissionCatalogController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Catalogos\CategoriaController;
use App\Http\Controllers\Catalogos\ProveedorController;
use App\Http\Controllers\Catalogos\ProductoController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\Inventario\InventarioController;
use Illuminate\Support\Facades\Route;

// Authentication endpoints
Route::prefix('auth')->group(function (): void {
    Route::middleware('guest')->post('/login', [AuthController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'show']);
        Route::post('/logout', [AuthController::class, 'destroy']);
    });
});

Route::middleware('guest')->get('/configuraciones/login', [ConfiguracionController::class, 'login']);

// Protected application endpoints
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/configuraciones/publicas', [ConfiguracionController::class, 'publicas']);

    Route::middleware('permission:dashboard.view')->get('/dashboard-data', function () {
        return response()->json([
            'message' => 'Dashboard API is ready.',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::prefix('admin')->group(function (): void {
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::put('/users/{user}', [UserManagementController::class, 'update']);
            Route::get('/users/catalogs', [UserManagementController::class, 'catalogs']);
        });

        Route::middleware('permission:roles.manage')->group(function (): void {
            Route::get('/roles', [RoleManagementController::class, 'index']);
            Route::post('/roles', [RoleManagementController::class, 'store']);
            Route::put('/roles/{role}', [RoleManagementController::class, 'update']);
            Route::get('/permissions', [PermissionCatalogController::class, 'index']);
        });
    });

    Route::prefix('catalogos')->middleware('permission:inventory.manage')->group(function (): void {
        // Categorias
        Route::get('/categorias', [CategoriaController::class, 'index']);
        Route::post('/categorias', [CategoriaController::class, 'store']);
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::patch('/categorias/{categoria}/toggle', [CategoriaController::class, 'toggle']);
        Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy']);

        // Productos
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{producto}', [ProductoController::class, 'update']);
        Route::patch('/productos/{producto}/toggle', [ProductoController::class, 'toggle']);
        Route::delete('/productos/{producto}', [ProductoController::class, 'destroy']);

        // Proveedores
        Route::get('/proveedores', [ProveedorController::class, 'index']);
        Route::post('/proveedores', [ProveedorController::class, 'store']);
        Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update']);
        Route::patch('/proveedores/{proveedor}/toggle', [ProveedorController::class, 'toggle']);
        Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy']);
    });

    // Rutas de compras
    Route::prefix('compras')->middleware('permission:purchases.view|purchases.create')->group(function (): void {
        Route::get('/', [CompraController::class, 'index'])->middleware('permission:purchases.view');
        Route::get('/catalogs', [CompraController::class, 'catalogs'])->middleware('permission:purchases.create');
        Route::post('/', [CompraController::class, 'store'])->middleware('permission:purchases.create');
    });

    // Rutas de inventario
    Route::prefix('inventario')->middleware('permission:inventory.view|inventory.manage')->group(function (): void {
        Route::get('/existencias', [InventarioController::class, 'existencias'])->middleware('permission:inventory.view');
        Route::get('/movimientos', [InventarioController::class, 'movimientos'])->middleware('permission:inventory.view');
    });
});

// SPA entry point (exclude backend endpoints)
Route::view('/{path?}', 'app')
    ->where('path', '^(?!auth|configuraciones|dashboard-data|admin|catalogos|compras|inventario|sanctum).*$');
