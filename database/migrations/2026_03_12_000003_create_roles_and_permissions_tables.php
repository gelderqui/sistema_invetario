<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('module')->nullable();
            $table->string('module_label')->nullable();
            $table->string('module_icono')->nullable();
            $table->string('ruta')->nullable();
            $table->string('icono')->nullable();
            $table->unsignedSmallInteger('orden')->nullable();
            $table->string('description')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['name']);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
        });

        // Ahora que roles existe, agregar FK de role_id en users
        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Primero quitar la FK de users antes de eliminar roles
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};