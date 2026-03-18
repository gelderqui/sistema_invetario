<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha');
            $table->string('estado', 20)->default('activo');
            $table->decimal('total', 12, 4)->default(0);
            $table->timestamps();

            $table->foreign('venta_id')->references('id')->on('ventas')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id']);
            $table->index('estado');
            $table->index(['usuario_id', 'fecha', 'id'], 'devoluciones_usuario_fecha_id_idx');
        });

        Schema::create('detalle_devoluciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('devolucion_id');
            $table->unsignedBigInteger('venta_detalle_id')->nullable();
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->foreign('devolucion_id')->references('id')->on('devoluciones')->cascadeOnDelete();
            $table->foreign('venta_detalle_id')->references('id')->on('venta_detalles')->nullOnDelete();
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            $table->index(['producto_id', 'devolucion_id']);
            $table->index('venta_detalle_id', 'detalle_devoluciones_venta_detalle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_devoluciones');
        Schema::dropIfExists('devoluciones');
    }
};
