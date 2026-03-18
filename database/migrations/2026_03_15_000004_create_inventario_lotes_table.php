<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_lotes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->decimal('cantidad_inicial', 12, 4);
            $table->decimal('cantidad_disponible', 12, 4);
            $table->decimal('costo_unitario', 12, 4);
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_entrada');
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles')->nullOnDelete();
            $table->index(['producto_id', 'fecha_entrada'], 'lotes_fifo_idx');
            $table->index(['producto_id', 'fecha_vencimiento'], 'lotes_vencimiento_idx');
            $table->index(['producto_id', 'cantidad_disponible', 'fecha_entrada'], 'lotes_fifo_stock_idx');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreign('lote_id')->references('id')->on('inventario_lotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropForeign(['lote_id']);
        });

        Schema::dropIfExists('inventario_lotes');
    }
};
