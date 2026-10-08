<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('clientes', 'clientes_nit_unique')) {
            Schema::table('clientes', function (Blueprint $table): void {
                $table->dropUnique('clientes_nit_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('clientes', 'clientes_nit_unique')) {
            Schema::table('clientes', function (Blueprint $table): void {
                $table->unique('nit', 'clientes_nit_unique');
            });
        }
    }
};
