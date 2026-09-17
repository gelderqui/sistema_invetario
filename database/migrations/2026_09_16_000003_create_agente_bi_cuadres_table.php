<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_bi_cuadres', function (Blueprint $table): void {
            $table->id();
            $table->decimal('capital', 14, 4)->default(0);
            $table->decimal('bi_debe_total', 14, 4)->default(0);
            $table->decimal('banco', 14, 4)->default(0);
            $table->decimal('caja', 14, 4)->default(0);
            $table->decimal('caja_chica', 14, 4)->default(0);
            $table->decimal('deuda_a_bi_total', 14, 4)->default(0);
            $table->decimal('total_financiado', 14, 4)->default(0);
            $table->decimal('total_ubicado', 14, 4)->default(0);
            $table->decimal('diferencia', 14, 4)->default(0);
            $table->unsignedBigInteger('agente_bi_arqueo_caja_chica_id')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('agente_bi_arqueo_caja_chica_id')
                ->references('id')
                ->on('agente_bi_arqueos_caja_chica')
                ->nullOnDelete();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_bi_cuadres');
    }
};
