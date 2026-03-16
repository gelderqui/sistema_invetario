<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_cuentas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 100);
            $table->string('tipo', 30)->default('efectivo');
            $table->string('descripcion', 255)->nullable();
            $table->decimal('saldo_actual', 12, 4)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_cuentas');
    }
};