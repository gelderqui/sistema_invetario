<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_bi_arqueos_caja_chica', function (Blueprint $table): void {
            $table->id();
            $table->decimal('monto_sistema', 14, 4)->nullable();
            $table->decimal('monto_contado', 14, 4)->default(0);
            $table->decimal('diferencia', 14, 4)->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id']);
        });

        Schema::create('agente_bi_arqueo_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agente_bi_arqueo_caja_chica_id');
            $table->enum('tipo', ['billete', 'moneda', 'paquete']);
            $table->decimal('denominacion', 10, 2);
            $table->unsignedInteger('unidades_por_paquete')->default(1);
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('agente_bi_arqueo_caja_chica_id')
                ->references('id')
                ->on('agente_bi_arqueos_caja_chica')
                ->cascadeOnDelete();
            $table->index('agente_bi_arqueo_caja_chica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_bi_arqueo_detalles');
        Schema::dropIfExists('agente_bi_arqueos_caja_chica');
    }
};
