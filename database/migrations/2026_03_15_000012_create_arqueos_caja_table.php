<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
