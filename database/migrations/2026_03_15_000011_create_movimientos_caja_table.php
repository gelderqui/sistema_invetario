<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
