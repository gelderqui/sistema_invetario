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
            ['name' => 'Dashboard',    'code' => 'dashboard',  'module' => 'dashboard',  'module_label' => null,             'module_icono' => null,                            'ruta' => '/',           'icono' => 'fa-solid fa-chart-line',     'orden' => 1],
            ['name' => 'Categorias',   'code' => 'categorias', 'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/categorias', 'icono' => 'fa-solid fa-tags',           'orden' => 3],
            ['name' => 'Proveedores',  'code' => 'proveedores','module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/proveedores','icono' => 'fa-solid fa-truck-field',    'orden' => 4],
            ['name' => 'Productos',    'code' => 'productos',  'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/productos',  'icono' => 'fa-solid fa-box',            'orden' => 5],
            ['name' => 'Clientes',     'code' => 'cliente',    'module' => 'catalogos',  'module_label' => 'Catalogo',       'module_icono' => 'fa-solid fa-boxes-stacked',    'ruta' => '/cliente',    'icono' => 'fa-solid fa-address-card',   'orden' => 6],
            ['name' => 'Compras',      'code' => 'compras',    'module' => 'compras',    'module_label' => null,             'module_icono' => null,                            'ruta' => '/compras',    'icono' => 'fa-solid fa-truck-ramp-box', 'orden' => 7],
            ['name' => 'Inventario',   'code' => 'inventario', 'module' => 'inventario', 'module_label' => null,             'module_icono' => null,                            'ruta' => '/inventario', 'icono' => 'fa-solid fa-warehouse',      'orden' => 8],
            ['name' => 'Usuarios',     'code' => 'users',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',             'ruta' => '/configuracion/usuarios',       'icono' => 'fa-solid fa-users',       'orden' => 20],
            ['name' => 'Roles',        'code' => 'roles',      'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',             'ruta' => '/configuracion/roles',          'icono' => 'fa-solid fa-user-shield', 'orden' => 21],
            ['name' => 'Configuraciones', 'code' => 'configuraciones', 'module' => 'configuracion', 'module_label' => 'Configuracion', 'module_icono' => 'fa-solid fa-gears',     'ruta' => '/configuracion/configuraciones', 'icono' => 'fa-solid fa-sliders', 'orden' => 22],
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
                'description' => 'Acceso general excepto configuracion y reportes.',
                'permissions' => [
                    'dashboard',
                    'categorias',
                    'proveedores',
                    'productos',
                    'cliente',
                    'compras',
                    'inventario',
                ],
            ],
            'almacenero' => [
                'name' => 'Almacenero',
                'description' => 'Gestion de stock y compras.',
                'permissions' => [
                    'dashboard',
                    'categorias',
                    'proveedores',
                    'productos',
                    'cliente',
                    'compras',
                    'inventario',
                ],
            ],
            'vendedor' => [
                'name' => 'Vendedor',
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
                'description' => 'Operacion de ventas y caja.',
                'permissions' => [
                    'dashboard',
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