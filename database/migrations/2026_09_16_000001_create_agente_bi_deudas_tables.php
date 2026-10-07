<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agente_bi_deudas', function (Blueprint $table): void {
            $table->id();
            $table->enum('tipo', ['cuenta_por_pagar', 'cuenta_por_cobrar']);
            $table->string('nombre_referencia', 150);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('monto_original', 14, 4)->default(0);
            $table->decimal('saldo_pendiente', 14, 4)->default(0);
            $table->dateTime('fecha_origen');
            $table->enum('estado', ['activa', 'pagada', 'anulada'])->default('activa');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('usuario_id', 'abi_deudas_usuario_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tipo', 'estado'], 'abi_deudas_tipo_estado_idx');
            $table->index(['fecha_origen', 'id'], 'abi_deudas_fecha_idx');
        });

        Schema::create('agente_bi_deuda_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agente_bi_deuda_id');
            $table->enum('tipo', ['cargo', 'abono', 'ajuste_aumenta', 'ajuste_disminuye', 'anulacion']);
            $table->decimal('monto', 14, 4)->default(0);
            $table->decimal('saldo_anterior', 14, 4)->default(0);
            $table->decimal('saldo_posterior', 14, 4)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('agente_bi_deuda_id', 'abi_mov_deuda_fk')
                ->references('id')
                ->on('agente_bi_deudas')
                ->cascadeOnDelete();
            $table->foreign('usuario_id', 'abi_mov_usuario_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['agente_bi_deuda_id', 'fecha'], 'abi_mov_deuda_fecha_idx');
        });

        Schema::create('agente_bi_arqueos_caja_chica', function (Blueprint $table): void {
            $table->id();
            $table->decimal('monto_sistema', 14, 4)->nullable();
            $table->decimal('monto_contado', 14, 4)->default(0);
            $table->decimal('diferencia', 14, 4)->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('usuario_id', 'abi_arqueos_usuario_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id'], 'abi_arqueos_fecha_idx');
        });

        Schema::create('agente_bi_arqueo_detalles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agente_bi_arqueo_caja_chica_id');
            $table->enum('tipo', ['billete', 'moneda', 'paquete']);
            $table->decimal('denominacion', 10, 2);
            $table->unsignedInteger('unidades_por_paquete')->default(1);
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->timestamps();

            $table->foreign('agente_bi_arqueo_caja_chica_id', 'abi_arqueo_det_arqueo_fk')
                ->references('id')
                ->on('agente_bi_arqueos_caja_chica')
                ->cascadeOnDelete();
            $table->index('agente_bi_arqueo_caja_chica_id', 'abi_arqueo_det_arqueo_idx');
        });

        Schema::create('agente_bi_cuadres', function (Blueprint $table): void {
            $table->id();
            $table->decimal('capital', 14, 4)->default(0);
            $table->decimal('cuentas_por_pagar_total', 14, 4)->default(0);
            $table->decimal('banco', 14, 4)->default(0);
            $table->decimal('caja', 14, 4)->default(0);
            $table->decimal('caja_chica', 14, 4)->default(0);
            $table->decimal('cuentas_por_cobrar_total', 14, 4)->default(0);
            $table->decimal('total_financiado', 14, 4)->default(0);
            $table->decimal('total_ubicado', 14, 4)->default(0);
            $table->decimal('diferencia', 14, 4)->default(0);
            $table->unsignedBigInteger('agente_bi_arqueo_caja_chica_id')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();

            $table->foreign('agente_bi_arqueo_caja_chica_id', 'abi_cuadres_arqueo_fk')
                ->references('id')
                ->on('agente_bi_arqueos_caja_chica')
                ->nullOnDelete();
            $table->foreign('usuario_id', 'abi_cuadres_usuario_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['fecha', 'id'], 'abi_cuadres_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_bi_cuadres');
        Schema::dropIfExists('agente_bi_arqueo_detalles');
        Schema::dropIfExists('agente_bi_arqueos_caja_chica');
        Schema::dropIfExists('agente_bi_deuda_movimientos');
        Schema::dropIfExists('agente_bi_deudas');
    }
};
