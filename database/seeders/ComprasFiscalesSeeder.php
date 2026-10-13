<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ComprasFiscalesSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::query()->updateOrCreate(
            ['codigo' => 'nit_empresa'],
            [
                'descripcion' => 'NIT al que deben emitirse las facturas de compra con crédito fiscal.',
                'value' => Configuracion::query()->where('codigo', 'nit_empresa')->value('value') ?? '',
                'activo' => true,
            ]
        );
    }
}
