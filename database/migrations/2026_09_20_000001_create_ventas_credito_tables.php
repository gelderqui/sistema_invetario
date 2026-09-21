<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_credito', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_id')->unique()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto_original', 12, 4);
            $table->decimal('saldo_pendiente', 12, 4);
            $table->string('estado', 20)->default('activa');
            $table->text('observaciones')->nullable();
            $table->foreignId('add_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mod_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
            $table->index(['estado', 'fecha_vencimiento']);
        });

        Schema::create('ventas_credito_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venta_credito_id')->constrained('ventas_credito')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->decimal('monto', 12, 4);
            $table->decimal('saldo_anterior', 12, 4);
            $table->decimal('saldo_posterior', 12, 4);
            $table->dateTime('fecha');
            $table->string('metodo_pago', 20)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['venta_credito_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_credito_movimientos');
        Schema::dropIfExists('ventas_credito');
    }
};
