<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->string('tipo_reintegro', 20)->nullable()->after('total');
            $table->decimal('monto_reembolso_efectivo', 12, 4)->default(0)->after('tipo_reintegro');
            $table->decimal('monto_aplicado_credito', 12, 4)->default(0)->after('monto_reembolso_efectivo');
            $table->foreignId('movimiento_caja_id')->nullable()->after('monto_aplicado_credito')->constrained('movimientos_caja')->nullOnDelete();
            $table->foreignId('venta_credito_movimiento_id')->nullable()->after('movimiento_caja_id')->constrained('ventas_credito_movimientos')->nullOnDelete();
            $table->foreignId('movimiento_caja_reversion_id')->nullable()->after('venta_credito_movimiento_id')->constrained('movimientos_caja')->nullOnDelete();
            $table->foreignId('venta_credito_movimiento_reversion_id')->nullable()->after('movimiento_caja_reversion_id')->constrained('ventas_credito_movimientos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('venta_credito_movimiento_reversion_id');
            $table->dropConstrainedForeignId('movimiento_caja_reversion_id');
            $table->dropConstrainedForeignId('venta_credito_movimiento_id');
            $table->dropConstrainedForeignId('movimiento_caja_id');
            $table->dropColumn(['tipo_reintegro', 'monto_reembolso_efectivo', 'monto_aplicado_credito']);
        });
    }
};
