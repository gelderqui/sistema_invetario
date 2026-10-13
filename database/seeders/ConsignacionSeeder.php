<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ConsignacionSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::query()->updateOrCreate(
            ['codigo' => 'habilitar_consignacion'],
            [
                'descripcion' => 'Habilita compras en consignación y cuentas por pagar a proveedores.',
                'value' => '0',
                'activo' => true,
            ]
        );

        $permission = Permission::query()->updateOrCreate(
            ['code' => 'proveedores_cuentas_por_pagar'],
            [
                'name' => 'Cuentas por pagar',
                'module' => 'operaciones',
                'module_label' => 'Compras',
                'module_icono' => 'fa-solid fa-briefcase',
                'ruta' => '/cuentas-por-pagar',
                'icono' => 'fa-solid fa-hand-holding-dollar',
                'orden' => 4,
                'activo' => true,
            ]
        );

        Role::query()
            ->whereIn('code', ['admin', 'operador', 'cajero'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }
}
