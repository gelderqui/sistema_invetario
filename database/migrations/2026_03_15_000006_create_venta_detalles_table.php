<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 12, 4);
            $table->string('unidad_medida', 30)->nullable();
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->timestamps();

            $table->foreign('venta_id')->references('id')->on('ventas')->cascadeOnDelete();
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            $table->index(['producto_id', 'venta_id']);
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreign('venta_id')->references('id')->on('ventas')->nullOnDelete();
            $table->foreign('venta_detalle_id')->references('id')->on('venta_detalles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropForeign(['venta_id']);
            $table->dropForeign(['venta_detalle_id']);
        });

        Schema::dropIfExists('venta_detalles');
    }
};
