<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->unsignedBigInteger('proveedor_id')->nullable()->after('categoria_id');
            $table->decimal('precio_venta', 12, 4)->default(0)->after('palabras_clave');
            $table->decimal('costo_promedio', 12, 4)->default(0)->after('precio_venta');
            $table->decimal('stock_actual', 12, 4)->default(0)->after('costo_promedio');
            $table->decimal('stock_minimo', 12, 4)->default(0)->after('stock_actual');
            $table->string('unidad_medida', 30)->default('unidad')->after('stock_minimo');
            $table->decimal('peso_referencial', 12, 4)->nullable()->after('unidad_medida');

            $table->foreign('proveedor_id')->references('id')->on('proveedores')->nullOnDelete();
            $table->index(['activo', 'stock_actual'], 'productos_activo_stock_idx');
            $table->index('proveedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropForeign(['proveedor_id']);
            $table->dropIndex('productos_activo_stock_idx');
            $table->dropIndex(['proveedor_id']);
            $table->dropColumn([
                'proveedor_id',
                'precio_venta',
                'costo_promedio',
                'stock_actual',
                'stock_minimo',
                'unidad_medida',
                'peso_referencial',
            ]);
        });
    }
};
