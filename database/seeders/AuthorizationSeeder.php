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
            ['name' => 'Dashboard',    'code' => 'dashboard',  'module' => 'dashboard',  'ruta' => '/',           'icono' => 'fa-solid fa-chart-line',     'orden' => 1],
            ['name' => 'Categorias',   'code' => 'categorias', 'module' => 'catalogos',  'ruta' => '/categorias', 'icono' => 'fa-solid fa-tags',           'orden' => 3],
            ['name' => 'Proveedores',  'code' => 'proveedores','module' => 'catalogos',  'ruta' => '/proveedores','icono' => 'fa-solid fa-truck-field',    'orden' => 4],
            ['name' => 'Productos',    'code' => 'productos',  'module' => 'catalogos',  'ruta' => '/productos',  'icono' => 'fa-solid fa-box',            'orden' => 5],
            ['name' => 'Clientes',     'code' => 'cliente',    'module' => 'catalogos',  'ruta' => '/cliente',    'icono' => 'fa-solid fa-address-card',   'orden' => 6],
            ['name' => 'Compras',      'code' => 'compras',    'module' => 'compras',    'ruta' => '/compras',    'icono' => 'fa-solid fa-truck-ramp-box', 'orden' => 7],
            ['name' => 'Inventario',   'code' => 'inventario', 'module' => 'inventario', 'ruta' => '/inventario', 'icono' => 'fa-solid fa-warehouse',      'orden' => 8],
            ['name' => 'Usuarios',     'code' => 'users',      'module' => 'admin',      'ruta' => '/usuarios',   'icono' => 'fa-solid fa-users',          'orden' => 9],
            ['name' => 'Roles',        'code' => 'roles',      'module' => 'admin',      'ruta' => '/roles',      'icono' => 'fa-solid fa-user-shield',    'orden' => 10],
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
                'description' => 'Operacion general con acceso amplio.',
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