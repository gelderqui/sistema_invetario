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
            ['name' => 'Apertura de caja',   'code' => 'caja_apertura',   'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/apertura',   'icono' => 'fa-solid fa-lock-open',      'orden' => 30],
            ['name' => 'Movimientos de caja','code' => 'caja_movimientos','module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/movimientos','icono' => 'fa-solid fa-money-bill-transfer', 'orden' => 31],
            ['name' => 'Arqueo de caja',     'code' => 'caja_arqueo',     'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/arqueo',     'icono' => 'fa-solid fa-scale-balanced', 'orden' => 32],
            ['name' => 'Cierre de caja',     'code' => 'caja_cierre',     'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/caja/cierre',     'icono' => 'fa-solid fa-lock',           'orden' => 33],
            ['name' => 'POS',                'code' => 'ventas',          'module' => 'caja',       'module_label' => 'Caja',      'module_icono' => 'fa-solid fa-cash-register', 'ruta' => '/ventas',          'icono' => 'fa-solid fa-cash-register',  'orden' => 34],
            ['name' => 'Compras',      'code' => 'compras',    'module' => 'compras',    'module_label' => null,             'module_icono' => null,                            'ruta' => '/compras',        'icono' => 'fa-solid fa-truck-ramp-box', 'orden' => 40],
            ['name' => 'Inventario',   'code' => 'inventario', 'module' => 'inventario', 'module_label' => null,             'module_icono' => null,                            'ruta' => '/inventario',     'icono' => 'fa-solid fa-warehouse',      'orden' => 60],
            ['name' => 'Gastos',       'code' => 'gastos',     'module' => 'gastos',     'module_label' => null,             'module_icono' => null,                            'ruta' => '/gastos',         'icono' => 'fa-solid fa-receipt',        'orden' => 70],
            ['name' => 'Categorias',   'code' => 'categorias', 'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/categorias',     'icono' => 'fa-solid fa-tags',           'orden' => 80],
            ['name' => 'Proveedores',  'code' => 'proveedores','module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/proveedores',    'icono' => 'fa-solid fa-truck-field',    'orden' => 81],
            ['name' => 'Productos',    'code' => 'productos',  'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/productos',      'icono' => 'fa-solid fa-box',            'orden' => 82],
            ['name' => 'Clientes',     'code' => 'cliente',    'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/clientes',       'icono' => 'fa-solid fa-address-card',   'orden' => 83],
            ['name' => 'Reportes',     'code' => 'reportes',   'module' => 'reportes',   'module_label' => null,             'module_icono' => null,                            'ruta' => '/reportes',       'icono' => 'fa-solid fa-chart-pie',      'orden' => 88],
            ['name' => 'Usuarios',     'code' => 'users',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',          'ruta' => '/usuarios',       'icono' => 'fa-solid fa-users',          'orden' => 90],
            ['name' => 'Roles',        'code' => 'roles',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',          'ruta' => '/roles',          'icono' => 'fa-solid fa-user-shield',    'orden' => 91],
            ['name' => 'Configuraciones', 'code' => 'configuraciones', 'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',  'ruta' => '/configuraciones','icono' => 'fa-solid fa-sliders',        'orden' => 92],
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

        $roles = [
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Acceso total al sistema.',
                'permissions' => Permission::query()->pluck('code')->all(),
            ],
            'operador' => [
                'name' => 'Operador',
                'description' => 'Acceso general excepto configuracion.',
                'permissions' => [
                    'dashboard',
                    'caja_apertura',
                    'caja_movimientos',
                    'caja_arqueo',
                    'caja_cierre',
                    'categorias',
                    'proveedores',
                    'productos',
                    'cliente',
                    'compras',
                    'ventas',
                    'inventario',
                    'gastos',
                    'reportes',
                ],
            ],
            'almacenero' => [
                'name' => 'Almacenero',
                'description' => 'Acceso a catalogo y compras.',
                'permissions' => [
                    'dashboard',
                    'categorias',
                    'proveedores',
                    'productos',
                    'cliente',
                    'compras',
                ],
            ],
            'cajero' => [
                'name' => 'Cajero',
                'description' => 'Acceso a caja.',
                'permissions' => [
                    'dashboard',
                    'caja_apertura',
                    'caja_movimientos',
                    'caja_arqueo',
                    'caja_cierre',
                    'ventas',
                    'gastos',
                    'reportes',
                ],
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

        $almaceneroRole = Role::query()->where('code', 'almacenero')->first();
        $vendedorRole = Role::query()->where('code', 'vendedor')->first();

        if ($almaceneroRole && $vendedorRole) {
            User::query()
                ->where('role_id', $vendedorRole->id)
                ->update(['role_id' => $almaceneroRole->id]);

            $vendedorRole->delete();
        }

        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

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
    }
}