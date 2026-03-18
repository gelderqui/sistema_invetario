<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->unique();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->date('fecha_venta');
            $table->string('estado', 20)->default('activo');
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->decimal('monto_recibido', 12, 4)->nullable();
            $table->decimal('cambio', 12, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('add_user')->nullable();
            $table->unsignedBigInteger('mod_user')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mod_user')->references('id')->on('users')->nullOnDelete();
            $table->index(['fecha_venta', 'id']);
            $table->index('estado');
            $table->index('metodo_pago');
            $table->index(['add_user', 'fecha_venta', 'id'], 'ventas_user_fecha_id_idx');
            $table->index(['add_user', 'estado', 'fecha_venta', 'id'], 'ventas_user_estado_fecha_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
