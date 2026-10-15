<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table): void {
            $table->string('nit', 30)->nullable()->after('nombre');
            $table->index('nit', 'proveedores_nit_idx');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table): void {
            $table->dropIndex('proveedores_nit_idx');
            $table->dropColumn('nit');
        });
    }
};
