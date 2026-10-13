<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->decimal('precio_mayorista', 12, 4)->nullable()->after('precio_venta');
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('tipo_cliente', 30)->default('minorista')->after('nombre');
            $table->index('tipo_cliente', 'clientes_tipo_cliente_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropIndex('clientes_tipo_cliente_idx');
            $table->dropColumn('tipo_cliente');
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('precio_mayorista');
        });
    }
};
