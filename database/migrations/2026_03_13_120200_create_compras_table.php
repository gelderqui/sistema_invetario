<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
