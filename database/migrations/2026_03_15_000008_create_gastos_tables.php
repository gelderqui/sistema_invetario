<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_gasto', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('gastos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tipo_gasto_id');
            $table->string('descripcion');
            $table->decimal('monto', 12, 4);
            $table->date('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->timestamps();

            $table->foreign('tipo_gasto_id')->references('id')->on('tipos_gasto')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id']);
            $table->index('metodo_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
        Schema::dropIfExists('tipos_gasto');
    }
};

