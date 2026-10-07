<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->dateTime('fecha_apertura');
            $table->decimal('monto_apertura', 12, 4)->default(0);
            $table->string('estado', 20)->default('abierta');
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('total_ventas', 12, 4)->default(0);
            $table->decimal('total_gastos', 12, 4)->default(0);
            $table->decimal('total_compras', 12, 4)->default(0);
            $table->decimal('total_ajustes', 12, 4)->default(0);
            $table->decimal('monto_sistema_final', 12, 4)->nullable();
            $table->decimal('monto_contado_final', 12, 4)->nullable();
            $table->decimal('diferencia', 12, 4)->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['usuario_id', 'estado']);
            $table->index('fecha_apertura');
        });

        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('caja_id');
            $table->string('tipo', 30);
            $table->decimal('monto', 12, 4);
            $table->string('descripcion', 255)->nullable();
            $table->string('referencia_tipo', 30)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['caja_id', 'fecha']);
            $table->index('tipo');
        });

        Schema::create('arqueos_caja', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('caja_id');
            $table->decimal('monto_sistema', 12, 4);
            $table->decimal('monto_contado', 12, 4);
            $table->decimal('diferencia', 12, 4);
            $table->json('detalle_billetes')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->dateTime('fecha');
            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['caja_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueos_caja');
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('cajas');
    }
};

