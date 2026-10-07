<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('contacto')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('add_user')->nullable();
            $table->unsignedBigInteger('mod_user')->nullable();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mod_user')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('compras', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->unique();
            $table->unsignedBigInteger('proveedor_id');
            $table->date('fecha_compra');
            $table->string('estado')->default('activo');
            $table->string('metodo_pago', 20)->default('caja_general');
            $table->decimal('total', 12, 4)->default(0);
            $table->string('tipo_documento', 20)->nullable();
            $table->string('numero_documento', 100)->nullable();
            $table->unsignedBigInteger('add_user')->nullable();
            $table->unsignedBigInteger('mod_user')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->restrictOnDelete();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mod_user')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha_compra', 'proveedor_id']);
            $table->index('metodo_pago');
        });

        Schema::create('compra_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 12, 4);
            $table->string('unidad_medida', 30)->nullable();
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->decimal('precio_venta_sugerido', 12, 4)->nullable();
            $table->decimal('precio_venta_aplicado', 12, 4)->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->timestamps();

            $table->foreign('compra_id')->references('id')->on('compras')->cascadeOnDelete();
            $table->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
            $table->index(['producto_id', 'fecha_caducidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};

