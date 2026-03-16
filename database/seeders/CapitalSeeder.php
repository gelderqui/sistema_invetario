<?php

namespace Database\Seeders;

use App\Models\CapitalCuenta;
use Illuminate\Database\Seeder;

class CapitalSeeder extends Seeder
{
    public function run(): void
    {
        $cuentas = [
            [
                'codigo' => 'caja_general',
                'nombre' => 'Caja general',
                'tipo' => 'efectivo',
                'descripcion' => 'Fondo central de efectivo del negocio.',
            ],
            [
                'codigo' => 'banco',
                'nombre' => 'Banco',
                'tipo' => 'banco',
                'descripcion' => 'Fondos depositados en cuenta bancaria.',
            ],
        ];

        foreach ($cuentas as $data) {
            $cuenta = CapitalCuenta::query()->firstOrNew(['codigo' => $data['codigo']]);
            $saldoActual = $cuenta->exists ? $cuenta->saldo_actual : 0;

            $cuenta->fill([
                ...$data,
                'saldo_actual' => $saldoActual,
                'activo' => true,
            ]);

            $cuenta->save();
        }
    }
}