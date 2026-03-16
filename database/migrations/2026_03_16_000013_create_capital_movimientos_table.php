<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 40);
            $table->unsignedBigInteger('cuenta_origen_id')->nullable();
            $table->unsignedBigInteger('cuenta_destino_id')->nullable();
            $table->decimal('monto', 12, 4);
            $table->string('descripcion', 255)->nullable();
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('cuenta_origen_id')->references('id')->on('capital_cuentas')->nullOnDelete();
            $table->foreign('cuenta_destino_id')->references('id')->on('capital_cuentas')->nullOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['fecha', 'id']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_movimientos');
    }
};