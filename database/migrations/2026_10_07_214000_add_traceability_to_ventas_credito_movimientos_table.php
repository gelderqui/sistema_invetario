<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_credito_movimientos', function (Blueprint $table): void {
            $table->foreignId('movimiento_caja_id')
                ->nullable()
                ->after('usuario_id')
                ->constrained('movimientos_caja')
                ->nullOnDelete();
            $table->foreignId('movimiento_origen_id')
                ->nullable()
                ->after('movimiento_caja_id')
                ->constrained('ventas_credito_movimientos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas_credito_movimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('movimiento_origen_id');
            $table->dropConstrainedForeignId('movimiento_caja_id');
        });
    }
};
