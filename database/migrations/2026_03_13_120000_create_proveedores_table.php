<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('contacto')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
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
        Schema::dropIfExists('proveedores');
    }
};
