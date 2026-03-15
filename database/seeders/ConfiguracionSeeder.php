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
                'descripcion' => 'Tiempo en sesion inactiva cuando se marca "Mantener sesion iniciada". De lo contrario dura 120 minutos.',
                'value' => '120',
                'activo' => true,
            ],
            [
                'codigo' => 'caja_alerta_faltante_monto',
                'descripcion' => 'Umbral monetario para alertar faltante alto en arqueo y cierre de caja.',
                'value' => '50',
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
