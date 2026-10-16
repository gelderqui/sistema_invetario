<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreignId('devolucion_id')->nullable()->after('venta_detalle_id')
                ->constrained('devoluciones')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE inventario_movimientos AS movimiento
            INNER JOIN devoluciones AS devolucion
                ON movimiento.referencia = CONCAT('DEV-', devolucion.id)
            SET movimiento.devolucion_id = devolucion.id
            WHERE movimiento.devolucion_id IS NULL
              AND movimiento.tipo IN ('devolucion_venta', 'anulacion_devolucion')
        SQL);
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('devolucion_id');
        });
    }
};
