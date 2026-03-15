<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('configuraciones')
            ->where('codigo', 'porcentaje_utilidad_compra')
            ->exists();

        if ($exists) {
            DB::table('configuraciones')
                ->where('codigo', 'porcentaje_utilidad_compra')
                ->where('value', '35')
                ->update([
                    'value' => '25',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('configuraciones')->insert([
            'codigo' => 'porcentaje_utilidad_compra',
            'descripcion' => 'Porcentaje entero usado en compras para sugerir el precio de venta sobre el costo.',
            'value' => '25',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('codigo', 'porcentaje_utilidad_compra')
            ->delete();
    }
};