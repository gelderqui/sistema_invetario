<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_bi_deudas', function (Blueprint $table): void {
            $table->id();
            $table->enum('tipo', ['bi_debe', 'deuda_a_bi']);
            $table->string('nombre_referencia', 150);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('monto_original', 14, 4)->default(0);
            $table->decimal('saldo_pendiente', 14, 4)->default(0);
            $table->dateTime('fecha_origen');
            $table->enum('estado', ['activa', 'pagada', 'anulada'])->default('activa');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tipo', 'estado']);
            $table->index(['fecha_origen', 'id']);
        });

        Schema::create('agente_bi_deuda_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agente_bi_deuda_id');
            $table->enum('tipo', ['cargo', 'abono', 'ajuste_aumenta', 'ajuste_disminuye', 'anulacion']);
            $table->decimal('monto', 14, 4)->default(0);
            $table->decimal('saldo_anterior', 14, 4)->default(0);
            $table->decimal('saldo_posterior', 14, 4)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('agente_bi_deuda_id')
                ->references('id')
                ->on('agente_bi_deudas')
                ->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['agente_bi_deuda_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_bi_deuda_movimientos');
        Schema::dropIfExists('agente_bi_deudas');
    }
};
