<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->foreign('categoria_id')->references('id')->on('categorias')->nullOnDelete();
            $table->string('nombre');
            $table->string('codigo_barra')->nullable()->unique();
            $table->text('palabras_clave')->nullable();
            $table->decimal('precio_venta', 12, 4)->default(0);
            $table->decimal('costo_promedio', 12, 4)->default(0);
            $table->decimal('precio_venta_promedio', 12, 4)->default(0);
            $table->decimal('costo_ultimo', 12, 4)->default(0);
            $table->unsignedInteger('stock_actual')->default(0);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->unsignedBigInteger('unidad_medida_id')->nullable();
            $table->boolean('control_vencimiento')->default(false);
            $table->unsignedSmallInteger('dias_alerta_vencimiento')->default(15);
            $table->decimal('peso_referencial', 12, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('add_user')->nullable();
            $table->unsignedBigInteger('mod_user')->nullable();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mod_user')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activo', 'stock_actual'], 'productos_activo_stock_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
