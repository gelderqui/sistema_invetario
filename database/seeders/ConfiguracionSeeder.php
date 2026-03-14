<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::query()->where('codigo', 'locale')->delete();

        $items = [
            [
                'codigo' => 'nombre_empresa',
                'descripcion' => 'Nombre comercial mostrado en el sistema.',
                'value' => 'weltixh',
                'activo' => true,
            ],
            [
                'codigo' => 'tiempo_sesion',
                'descripcion' => 'Tiempo de sesion inactiva en minutos.',
                'value' => '120',
                'activo' => true,
            ],
        ];

        foreach ($items as $item) {
            Configuracion::query()->updateOrCreate(
                ['codigo' => $item['codigo']],
                $item
            );
        }
    }
}
