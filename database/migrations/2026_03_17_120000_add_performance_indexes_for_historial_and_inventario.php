<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->index(['add_user', 'fecha_venta', 'id'], 'ventas_user_fecha_id_idx');
            $table->index(['add_user', 'estado', 'fecha_venta', 'id'], 'ventas_user_estado_fecha_id_idx');
        });

        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->index(['usuario_id', 'fecha', 'id'], 'devoluciones_usuario_fecha_id_idx');
        });

        Schema::table('detalle_devoluciones', function (Blueprint $table): void {
            $table->index('venta_detalle_id', 'detalle_devoluciones_venta_detalle_idx');
        });

        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->index(['producto_id', 'cantidad_disponible', 'fecha_entrada'], 'lotes_fifo_stock_idx');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->index(['add_user', 'producto_id'], 'inventario_movimientos_user_producto_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropIndex('inventario_movimientos_user_producto_idx');
        });

        Schema::table('inventario_lotes', function (Blueprint $table): void {
            $table->dropIndex('lotes_fifo_stock_idx');
        });

        Schema::table('detalle_devoluciones', function (Blueprint $table): void {
            $table->dropIndex('detalle_devoluciones_venta_detalle_idx');
        });

        Schema::table('devoluciones', function (Blueprint $table): void {
            $table->dropIndex('devoluciones_usuario_fecha_id_idx');
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropIndex('ventas_user_estado_fecha_id_idx');
            $table->dropIndex('ventas_user_fecha_id_idx');
        });
    }
};
