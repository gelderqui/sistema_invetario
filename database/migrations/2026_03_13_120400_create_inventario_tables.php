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

        Schema::create('inventario_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('venta_detalle_id')->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->string('tipo', 20);
            $table->decimal('cantidad', 12, 4);
            $table->decimal('stock_anterior', 12, 4)->default(0);
            $table->decimal('stock_nuevo', 12, 4)->default(0);
            $table->decimal('costo_unitario', 12, 4)->nullable();
            $table->decimal('precio_venta', 12, 4)->nullable();
            $table->string('referencia')->nullable();
            $table->text('nota')->nullable();
            $table->unsignedBigInteger('add_user')->nullable();
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            $table->foreign('compra_id')->references('id')->on('compras')->nullOnDelete();
            $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles')->nullOnDelete();
            $table->foreign('lote_id')->references('id')->on('inventario_lotes')->nullOnDelete();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->index(['producto_id', 'created_at']);
            $table->index('venta_id');
            $table->index('venta_detalle_id');
            $table->index('lote_id');
            $table->index(['add_user', 'producto_id'], 'inventario_movimientos_user_producto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('inventario_lotes');
    }
};
