<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('ruta')->nullable()->after('module');
            $table->string('icono')->nullable()->after('ruta');
            $table->unsignedSmallInteger('orden')->nullable()->after('icono');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn(['ruta', 'icono', 'orden']);
        });
    }
};
