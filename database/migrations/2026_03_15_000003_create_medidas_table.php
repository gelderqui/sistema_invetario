<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_unidad_medida', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->string('abreviatura', 10)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Agregar FK en productos (la columna unidad_medida_id ya fue creada antes)
        Schema::table('productos', function (Blueprint $table): void {
            $table->foreign('unidad_medida_id')->references('id')->on('productos_unidad_medida')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->dropForeign(['unidad_medida_id']);
        });
        Schema::dropIfExists('productos_unidad_medida');
    }
};
