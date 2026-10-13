<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('compras', 'es_consignacion')) {
            Schema::table('compras', function (Blueprint $table): void {
                $table->boolean('es_consignacion')->default(false)->after('metodo_pago');
                $table->index('es_consignacion');
            });
        }

        if (! Schema::hasColumn('inventario_lotes', 'es_consignacion')) {
            Schema::table('inventario_lotes', function (Blueprint $table): void {
                $table->boolean('es_consignacion')->default(false)->after('compra_detalle_id');
                $table->index('es_consignacion');
            });
        }

        if (! Schema::hasTable('proveedor_cuentas_por_pagar')) Schema::create('proveedor_cuentas_por_pagar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('venta_detalle_id')->nullable();
            $table->unsignedBigInteger('inventario_movimiento_id')->unique();
            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('monto_original', 12, 4);
            $table->decimal('saldo_pendiente', 12, 4);
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('add_user')->nullable();
            $table->timestamps();
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->restrictOnDelete();
            $table->foreign('compra_id')->references('id')->on('compras')->restrictOnDelete();
            $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles')->nullOnDelete();
            $table->foreign('venta_id')->references('id')->on('ventas')->restrictOnDelete();
            $table->foreign('venta_detalle_id')->references('id')->on('venta_detalles')->nullOnDelete();
            $table->foreign('inventario_movimiento_id')->references('id')->on('inventario_movimientos')->restrictOnDelete();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->index(['proveedor_id', 'estado']);
        });

        if (! Schema::hasTable('proveedor_pagos')) Schema::create('proveedor_pagos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->string('metodo_pago', 20);
            $table->decimal('monto', 12, 4);
            $table->date('fecha');
            $table->string('numero', 40)->unique();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('add_user')->nullable();
            $table->timestamps();
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->restrictOnDelete();
            $table->foreign('add_user')->references('id')->on('users')->nullOnDelete();
            $table->index(['proveedor_id', 'fecha']);
        });

        if (! Schema::hasTable('proveedor_pago_aplicaciones')) Schema::create('proveedor_pago_aplicaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proveedor_pago_id');
            $table->unsignedBigInteger('proveedor_cuenta_por_pagar_id');
            $table->decimal('monto', 12, 4);
            $table->timestamps();
            $table->foreign('proveedor_pago_id', 'ppa_pago_fk')->references('id')->on('proveedor_pagos')->cascadeOnDelete();
            $table->foreign('proveedor_cuenta_por_pagar_id', 'ppa_cuenta_fk')->references('id')->on('proveedor_cuentas_por_pagar')->restrictOnDelete();
            $table->unique(['proveedor_pago_id', 'proveedor_cuenta_por_pagar_id'], 'proveedor_pago_cuenta_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_pago_aplicaciones');
        Schema::dropIfExists('proveedor_pagos');
        Schema::dropIfExists('proveedor_cuentas_por_pagar');
        Schema::table('inventario_lotes', fn (Blueprint $table) => $table->dropColumn('es_consignacion'));
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn('es_consignacion'));
    }
};
