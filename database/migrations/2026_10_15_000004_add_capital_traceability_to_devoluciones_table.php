<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->foreignId('movimiento_capital_id')->nullable()->after('movimiento_caja_id')
                ->constrained('capital_movimientos')->nullOnDelete();
            $table->foreignId('movimiento_capital_reversion_id')->nullable()->after('movimiento_caja_reversion_id')
                ->constrained('capital_movimientos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('movimiento_capital_reversion_id');
            $table->dropConstrainedForeignId('movimiento_capital_id');
        });
    }
};
