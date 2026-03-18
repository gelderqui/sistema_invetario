<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            if (! Schema::hasColumn('compras', 'metodo_pago')) {
                $table->string('metodo_pago', 20)->default('caja_general')->after('estado');
                $table->index('metodo_pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table): void {
            if (Schema::hasColumn('compras', 'metodo_pago')) {
                $table->dropIndex(['metodo_pago']);
                $table->dropColumn('metodo_pago');
            }
        });
    }
};
