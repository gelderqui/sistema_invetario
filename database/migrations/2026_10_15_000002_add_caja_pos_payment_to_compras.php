<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            $table->foreignId('caja_id')
                ->nullable()
                ->after('metodo_pago')
                ->constrained('cajas')
                ->restrictOnDelete();

            $table->index(['caja_id', 'estado'], 'compras_caja_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            $table->dropIndex('compras_caja_estado_idx');
            $table->dropConstrainedForeignId('caja_id');
        });
    }
};
