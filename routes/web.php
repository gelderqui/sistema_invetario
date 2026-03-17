<?php

use App\Http\Controllers\Admin\PermissionCatalogController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Caja\CajaController;
use App\Http\Controllers\Capital\CapitalController;
use App\Http\Controllers\Catalogos\CategoriaController;
use App\Http\Controllers\Catalogos\ClienteController;
use App\Http\Controllers\Catalogos\ProductoUnidadMedidaController;
use App\Http\Controllers\Catalogos\ProveedorController;
use App\Http\Controllers\Catalogos\ProductoController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gastos\GastoController;
use App\Http\Controllers\Inventario\InventarioController;
use App\Http\Controllers\Inventario\AjusteInventarioController;
use App\Http\Controllers\Ventas\DevolucionController;
use App\Http\Controllers\Ventas\VentaController;
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

    Route::middleware(['auth:sanctum', 'ajax'])->group(function (): void {
        Route::get('/configuraciones/get/publicas', [ConfiguracionController::class, 'publicas']);
    });

    Route::middleware(['auth:sanctum', 'permission', 'ajax'])->group(function (): void {
        Route::get('/dashboard/get', [DashboardController::class, 'index']);

        Route::prefix('usuarios')->group(function (): void {
            Route::get('/get', [UserManagementController::class, 'index']);
            Route::post('/store', [UserManagementController::class, 'store']);
            Route::put('/update/{user}', [UserManagementController::class, 'update']);
            Route::delete('/destroy/{user}', [UserManagementController::class, 'destroy']);
            Route::get('/get/catalogs', [UserManagementController::class, 'catalogs']);
        });

        Route::prefix('roles')->group(function (): void {
            Route::get('/get', [RoleManagementController::class, 'index']);
            Route::post('/store', [RoleManagementController::class, 'store']);
            Route::put('/update/{role}', [RoleManagementController::class, 'update']);
            Route::delete('/destroy/{role}', [RoleManagementController::class, 'destroy']);
        });

        Route::get('/permissions/get', [PermissionCatalogController::class, 'index'])->middleware('permission:roles');

        Route::prefix('configuraciones')->group(function (): void {
            Route::get('/get', [ConfiguracionController::class, 'index']);
            Route::put('/update/{configuracion}', [ConfiguracionController::class, 'update']);
            Route::patch('/toggle/{configuracion}', [ConfiguracionController::class, 'toggle']);
            Route::delete('/destroy/{configuracion}', [ConfiguracionController::class, 'destroy']);
        });

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

        Route::prefix('medidas')->group(function (): void {
            Route::get('/get', [ProductoUnidadMedidaController::class, 'index'])->middleware('permission:productos');
        });

        Route::prefix('compras')->group(function (): void {
            Route::get('/get', [CompraController::class, 'index']);
            Route::get('/get/catalogs', [CompraController::class, 'catalogs']);
            Route::get('/get/{compra}', [CompraController::class, 'show']);
            Route::post('/store', [CompraController::class, 'store']);
            Route::patch('/anular/{compra}', [CompraController::class, 'anular']);
        });

        Route::prefix('caja')->group(function (): void {
            Route::get('/get/estado', [CajaController::class, 'estado'])->middleware('permission:caja_movimientos|caja_arqueo|caja_cierre|caja_apertura');
            Route::get('/get/movimientos', [CajaController::class, 'movimientos'])->middleware('permission:caja_movimientos');
            Route::get('/get/catalogs', [CajaController::class, 'catalogs'])->middleware('permission:caja_movimientos');
            Route::post('/apertura', [CajaController::class, 'apertura'])->middleware('permission:caja_apertura');
            Route::post('/movimientos/ajuste', [CajaController::class, 'registrarAjuste'])->middleware('permission:caja_movimientos');
            Route::post('/arqueo', [CajaController::class, 'arqueo'])->middleware('permission:caja_arqueo');
            Route::post('/cierre', [CajaController::class, 'cierre'])->middleware('permission:caja_cierre');
        });

        Route::prefix('capital')->group(function (): void {
            Route::get('/get', [CapitalController::class, 'index'])->middleware('permission:capital');
            Route::get('/get/catalogs', [CapitalController::class, 'catalogs'])->middleware('permission:capital');
            Route::post('/store', [CapitalController::class, 'store'])->middleware('permission:capital');
        });

        Route::prefix('ventas')->group(function (): void {
            Route::get('/get', [VentaController::class, 'index'])->middleware('permission:ventas');
            Route::get('/historial/get', [VentaController::class, 'historial'])->middleware('permission:historial_ventas|ventas');
            Route::get('/get/catalogs', [VentaController::class, 'catalogs'])->middleware('permission:ventas');
            Route::post('/store', [VentaController::class, 'store'])->middleware('permission:ventas');
            Route::patch('/anular/{venta}', [VentaController::class, 'anular'])->middleware('permission:ventas|historial_ventas');

            Route::prefix('devoluciones')->group(function (): void {
                Route::get('/get', [DevolucionController::class, 'index'])->middleware('permission:devoluciones');
                Route::get('/get/catalogs', [DevolucionController::class, 'catalogs'])->middleware('permission:devoluciones');
                Route::post('/store', [DevolucionController::class, 'store'])->middleware('permission:devoluciones');
                Route::patch('/anular/{devolucion}', [DevolucionController::class, 'anular'])->middleware('permission:devoluciones|historial_ventas');
            });
        });

        Route::prefix('gastos')->group(function (): void {
            Route::get('/get', [GastoController::class, 'index']);
            Route::get('/get/catalogs', [GastoController::class, 'catalogs']);
            Route::post('/store', [GastoController::class, 'store']);
        });

        Route::prefix('inventario')->group(function (): void {
            Route::get('/existencias/get', [InventarioController::class, 'existencias'])->middleware('permission:inventario');
            Route::get('/movimientos/get', [InventarioController::class, 'movimientos'])->middleware('permission:inventario_movimientos|inventario');
            Route::get('/alertas/get', [InventarioController::class, 'alertas'])->middleware('permission:inventario_alertas|inventario');

            Route::prefix('ajustes')->group(function (): void {
                Route::get('/get', [AjusteInventarioController::class, 'index'])->middleware('permission:inventario_ajustes');
                Route::get('/get/catalogs', [AjusteInventarioController::class, 'catalogs'])->middleware('permission:inventario_ajustes');
                Route::post('/store', [AjusteInventarioController::class, 'store'])->middleware('permission:inventario_ajustes');
            });
        });
    });

    // Tickets PDF deben poder abrirse en iframe/window.open, por eso no usan middleware ajax.
    Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
        Route::prefix('ventas')->group(function (): void {
            Route::get('/{venta}/ticket', [VentaController::class, 'ticket'])->middleware('permission:ventas|historial_ventas');

            Route::prefix('devoluciones')->group(function (): void {
                Route::get('/{devolucion}/ticket', [DevolucionController::class, 'ticket'])->middleware('permission:devoluciones|historial_ventas');
            });
        });
    });
});
 
// SPA entry point
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|sanctum).*$');
