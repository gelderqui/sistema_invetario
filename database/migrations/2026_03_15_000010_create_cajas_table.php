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
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
