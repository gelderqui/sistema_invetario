<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los precios, costos y totales comerciales se manejan como moneda y no
     * deben conservar fracciones menores a un centavo. Al reducir DECIMAL se
     * redondean también los valores históricos que estaban a cuatro decimales.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->decimal('precio_venta', 12, 2)->default(0)->change();
            $table->decimal('precio_mayorista', 12, 2)->nullable()->change();
            $table->decimal('costo_promedio', 12, 2)->default(0)->change();
            $table->decimal('precio_venta_promedio', 12, 2)->default(0)->change();
            $table->decimal('costo_ultimo', 12, 2)->default(0)->change();
        });

        Schema::table('compras', function (Blueprint $table): void {
            $table->decimal('total', 12, 2)->default(0)->change();
        });

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 2)->change();
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('precio_venta_sugerido', 12, 2)->nullable()->change();
            $table->decimal('precio_venta_aplicado', 12, 2)->nullable()->change();
        });

        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 2)->change();
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 2)->nullable()->change();
            $table->decimal('precio_venta', 12, 2)->nullable()->change();
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('descuento', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->default(0)->change();
            $table->decimal('monto_recibido', 12, 2)->nullable()->change();
            $table->decimal('cambio', 12, 2)->nullable()->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('precio_unitario', 12, 2)->change();
            $table->decimal('subtotal', 12, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->decimal('precio_venta', 12, 4)->default(0)->change();
            $table->decimal('precio_mayorista', 12, 4)->nullable()->change();
            $table->decimal('costo_promedio', 12, 4)->default(0)->change();
            $table->decimal('precio_venta_promedio', 12, 4)->default(0)->change();
            $table->decimal('costo_ultimo', 12, 4)->default(0)->change();
        });

        Schema::table('compras', function (Blueprint $table): void {
            $table->decimal('total', 12, 4)->default(0)->change();
        });

        Schema::table('compra_detalles', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 4)->change();
            $table->decimal('subtotal', 12, 4)->change();
            $table->decimal('precio_venta_sugerido', 12, 4)->nullable()->change();
            $table->decimal('precio_venta_aplicado', 12, 4)->nullable()->change();
        });

        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 4)->change();
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->decimal('costo_unitario', 12, 4)->nullable()->change();
            $table->decimal('precio_venta', 12, 4)->nullable()->change();
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->decimal('subtotal', 12, 4)->default(0)->change();
            $table->decimal('descuento', 12, 4)->default(0)->change();
            $table->decimal('total', 12, 4)->default(0)->change();
            $table->decimal('monto_recibido', 12, 4)->nullable()->change();
            $table->decimal('cambio', 12, 4)->nullable()->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table): void {
            $table->decimal('precio_unitario', 12, 4)->change();
            $table->decimal('subtotal', 12, 4)->change();
        });
    }
};
