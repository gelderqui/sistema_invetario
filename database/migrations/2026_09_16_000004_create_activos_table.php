<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('categoria', 100);
            $table->string('ubicacion', 150)->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('numero_serie', 100)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->decimal('costo_compra', 14, 4)->nullable();
            $table->enum('estado', ['activo', 'mantenimiento', 'danado', 'baja'])->default('activo');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['activo', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
