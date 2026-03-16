<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Dashboard',    'code' => 'dashboard',  'module' => 'dashboard',  'module_label' => null,             'module_icono' => null,                            'ruta' => '/',               'icono' => 'fa-solid fa-chart-line',     'orden' => 10],
            ['name' => 'Reportes',     'code' => 'reportes',   'module' => 'reportes',    'module_label' => null,            'module_icono' => null,                             'ruta' => '/reportes',              'icono' => 'fa-solid fa-chart-pie',        'orden' => 20],
            ['name' => 'Apertura de caja',   'code' => 'caja_apertura',   'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/apertura',   'icono' => 'fa-solid fa-lock-open',      'orden' => 30],
            ['name' => 'Movimientos de caja','code' => 'caja_movimientos','module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/movimientos','icono' => 'fa-solid fa-money-bill-transfer', 'orden' => 31],
            ['name' => 'Arqueo de caja',     'code' => 'caja_arqueo',     'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/arqueo',     'icono' => 'fa-solid fa-scale-balanced', 'orden' => 32],
            ['name' => 'Cierre de caja',     'code' => 'caja_cierre',     'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/cierre',     'icono' => 'fa-solid fa-lock',           'orden' => 33],
            ['name' => 'POS',                'code' => 'ventas',          'module' => 'ventas',     'module_label' => 'Ventas',    'module_icono' => 'fa-solid fa-cart-shopping', 'ruta' => '/ventas',              'icono' => 'fa-solid fa-cash-register',  'orden' => 40],
            ['name' => 'Clientes',           'code' => 'cliente',         'module' => 'ventas',     'module_label' => 'Ventas',    'module_icono' => 'fa-solid fa-cart-shopping', 'ruta' => '/clientes',            'icono' => 'fa-solid fa-address-card',   'orden' => 41],
            ['name' => 'Devoluciones',       'code' => 'devoluciones',    'module' => 'ventas',     'module_label' => 'Ventas',    'module_icono' => 'fa-solid fa-cart-shopping', 'ruta' => '/ventas/devoluciones', 'icono' => 'fa-solid fa-rotate-left',    'orden' => 42],
            ['name' => 'Historial',          'code' => 'historial_ventas','module' => 'ventas',     'module_label' => 'Ventas',    'module_icono' => 'fa-solid fa-cart-shopping', 'ruta' => '/ventas/historial',    'icono' => 'fa-solid fa-clock-rotate-left', 'orden' => 43],
            ['name' => 'Proveedores',  'code' => 'proveedores','module' => 'operaciones', 'module_label' => 'Operaciones', 'module_icono' => 'fa-solid fa-briefcase',        'ruta' => '/proveedores',           'icono' => 'fa-solid fa-truck-field',     'orden' => 49],
            ['name' => 'Compras',      'code' => 'compras',    'module' => 'operaciones', 'module_label' => 'Operaciones', 'module_icono' => 'fa-solid fa-briefcase',        'ruta' => '/compras',               'icono' => 'fa-solid fa-truck-ramp-box',  'orden' => 50],
            ['name' => 'Inventario',   'code' => 'inventario', 'module' => 'operaciones', 'module_label' => 'Operaciones', 'module_icono' => 'fa-solid fa-briefcase',        'ruta' => '/inventario/stock',      'icono' => 'fa-solid fa-warehouse',       'orden' => 51],
            ['name' => 'Gastos',       'code' => 'gastos',     'module' => 'operaciones', 'module_label' => 'Operaciones', 'module_icono' => 'fa-solid fa-briefcase',        'ruta' => '/gastos',                'icono' => 'fa-solid fa-receipt',         'orden' => 52],
            ['name' => 'Movimientos inventario', 'code' => 'inventario_movimientos', 'module' => 'inventario', 'module_label' => null,      'module_icono' => null,                            'ruta' => null,                      'icono' => 'fa-solid fa-arrow-right-arrow-left', 'orden' => 53],
            ['name' => 'Ajustes inventario', 'code' => 'inventario_ajustes',     'module' => 'inventario', 'module_label' => null,        'module_icono' => null,                            'ruta' => null,                      'icono' => 'fa-solid fa-screwdriver-wrench', 'orden' => 54],
            ['name' => 'Alertas stock',      'code' => 'inventario_alertas',     'module' => 'inventario', 'module_label' => null,        'module_icono' => null,                            'ruta' => null,                      'icono' => 'fa-solid fa-triangle-exclamation', 'orden' => 55],
            ['name' => 'Productos vencidos', 'code' => 'inventario_vencidos',    'module' => 'inventario', 'module_label' => null,        'module_icono' => null,                            'ruta' => null,                      'icono' => 'fa-solid fa-calendar-xmark',  'orden' => 56],
            ['name' => 'Categorias',   'code' => 'categorias', 'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/categorias',     'icono' => 'fa-solid fa-tags',            'orden' => 61],
            ['name' => 'Productos',    'code' => 'productos',  'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/productos',      'icono' => 'fa-solid fa-box',             'orden' => 62],
            ['name' => 'Usuarios',     'code' => 'users',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',          'ruta' => '/usuarios',       'icono' => 'fa-solid fa-users',           'orden' => 70],
            ['name' => 'Roles',        'code' => 'roles',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',          'ruta' => '/roles',          'icono' => 'fa-solid fa-user-shield',     'orden' => 71],
            ['name' => 'Configuraciones', 'code' => 'configuraciones', 'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',  'ruta' => '/configuraciones','icono' => 'fa-solid fa-sliders',         'orden' => 72],
        ];

        foreach ($permissions as $permissionData) {
            Permission::query()->updateOrCreate(
                ['code' => $permissionData['code']],
                [
                    ...$permissionData,
                    'activo' => true,
                ]
            );
        }

        $allPermissionCodes = Permission::query()->pluck('code')->values();

        $roles = [
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Acceso total al sistema.',
                'permissions' => $allPermissionCodes->all(),
            ],
            'operador' => [
                'name' => 'Operador',
                'description' => 'Acceso general excepto configuracion.',
                'permissions' => $allPermissionCodes
                    ->reject(fn (string $code): bool => in_array($code, ['users', 'roles', 'configuraciones'], true))
                    ->values()
                    ->all(),
            ],
            'cajero' => [
                'name' => 'Cajero',
                'description' => 'Acceso a todo excepto configuracion y reportes.',
                'permissions' => $allPermissionCodes
                    ->reject(fn (string $code): bool => in_array($code, ['users', 'roles', 'configuraciones', 'reportes'], true))
                    ->values()
                    ->all(),
            ],
        ];

        foreach ($roles as $code => $roleData) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                    'activo' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('code', $roleData['permissions'])
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        $operadorRole = Role::query()->where('code', 'operador')->first();
        $almaceneroRole = Role::query()->where('code', 'almacenero')->first();
        $vendedorRole = Role::query()->where('code', 'vendedor')->first();

        if ($operadorRole && $almaceneroRole) {
            User::query()
                ->where('role_id', $almaceneroRole->id)
                ->update(['role_id' => $operadorRole->id]);

            $almaceneroRole->delete();
        }

        if ($operadorRole && $vendedorRole) {
            User::query()
                ->where('role_id', $vendedorRole->id)
                ->update(['role_id' => $operadorRole->id]);

            $vendedorRole->delete();
        }

        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();
        $operadorRole = Role::query()->where('code', 'operador')->firstOrFail();
        $cajeroRole = Role::query()->where('code', 'cajero')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@admin.local'],
            [
                'username' => 'admin',
                'name' => 'Administrador General',
                'telefono' => null,
                'activo' => true,
                'role_id' => $adminRole->id,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'operador@admin.local'],
            [
                'username' => 'operador',
                'name' => 'Usuario Operador',
                'telefono' => null,
                'activo' => true,
                'role_id' => $operadorRole->id,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'cajero@admin.local'],
            [
                'username' => 'cajero',
                'name' => 'Usuario Cajero',
                'telefono' => null,
                'activo' => true,
                'role_id' => $cajeroRole->id,
                'password' => Hash::make('password'),
            ]
        );
    }
}