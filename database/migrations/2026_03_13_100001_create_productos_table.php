<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->foreign('categoria_id')->references('id')->on('categorias')->nullOnDelete();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('codigo_barra')->nullable()->unique();
            $table->text('detalle')->nullable();
            $table->text('palabras_clave')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('add_user')->nullable();
            $table->unsignedBigInteger('mod_user')->nullable();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->foreign('mod_user')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
