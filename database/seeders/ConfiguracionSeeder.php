<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::query()->where('codigo', 'locale')->delete();
        Configuracion::query()->where('codigo', 'devolucion_limite_minutos_cajero')->delete();

        $items = [
            [
                'codigo' => 'nombre_empresa',
                'descripcion' => 'Nombre comercial del sistema.',
                'value' => 'weltixh',
                'activo' => true,
            ],
            [
                'codigo' => 'tiempo_sesion',
                'descripcion' => 'Dias de sesion inactiva con "Mantener sesion iniciada".',
                'value' => '1',
                'activo' => true,
            ],
            [
                'codigo' => 'caja_alerta_faltante_monto',
                'descripcion' => 'Umbral en quetzales (Q) para alerta de faltante en caja.',
                'value' => '50',
                'activo' => true,
            ],
            [
                'codigo' => 'devolucion_limite_dias_cajero',
                'descripcion' => 'Dias maximos para que cajero registre devoluciones.',
                'value' => '1',
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
