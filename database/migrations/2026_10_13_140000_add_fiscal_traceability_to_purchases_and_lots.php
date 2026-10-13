<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            $table->string('factura_receptor', 20)->nullable()->after('numero_documento');
            $table->string('nit_receptor', 30)->nullable()->after('factura_receptor');
            $table->string('nit_proveedor', 30)->nullable()->after('nit_receptor');
            $table->string('serie_documento', 50)->nullable()->after('nit_proveedor');
            $table->index(['tipo_documento', 'factura_receptor', 'fecha_compra'], 'compras_fiscal_idx');
        });

        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->string('respaldo_fiscal', 20)->default('sin_factura')->after('es_consignacion');
            $table->index(['producto_id', 'respaldo_fiscal', 'cantidad_disponible'], 'lotes_fiscal_stock_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->dropIndex('lotes_fiscal_stock_idx');
            $table->dropColumn('respaldo_fiscal');
        });
        Schema::table('compras', function (Blueprint $table): void {
            $table->dropIndex('compras_fiscal_idx');
            $table->dropColumn(['factura_receptor', 'nit_receptor', 'nit_proveedor', 'serie_documento']);
        });
    }
};
