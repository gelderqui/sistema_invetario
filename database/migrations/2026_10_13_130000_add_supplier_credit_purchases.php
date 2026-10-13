<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            $table->string('tipo_compra', 30)->default('contado')->after('metodo_pago');
            $table->index('tipo_compra');
            $table->string('metodo_pago', 20)->nullable()->change();
        });

        // Las compras existentes siguen siendo contado, excepto las que ya eran consignación.
        DB::table('compras')->where('es_consignacion', true)->update(['tipo_compra' => 'consignacion']);

        Schema::table('proveedor_cuentas_por_pagar', function (Blueprint $table): void {
            $table->string('origen', 30)->default('consignacion_venta')->after('proveedor_id');
            $table->date('fecha_vencimiento')->nullable()->after('saldo_pendiente');
            $table->unsignedBigInteger('venta_id')->nullable()->change();
            $table->unsignedBigInteger('inventario_movimiento_id')->nullable()->change();
            $table->decimal('cantidad', 12, 4)->nullable()->change();
            $table->decimal('costo_unitario', 12, 4)->nullable()->change();
            $table->index(['origen', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_cuentas_por_pagar', function (Blueprint $table): void {
            $table->dropIndex(['origen', 'estado']);
            $table->dropColumn(['origen', 'fecha_vencimiento']);
        });

        Schema::table('compras', function (Blueprint $table): void {
            $table->dropIndex(['tipo_compra']);
            $table->dropColumn('tipo_compra');
        });
    }
};
