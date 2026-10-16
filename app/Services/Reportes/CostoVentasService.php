<?php

namespace App\Services\Reportes;

use App\Models\InventarioMovimiento;
use Carbon\CarbonInterface;

class CostoVentasService
{
    /** @return array{ventas: float, devoluciones: float, neto: float} */
    public function resumen(CarbonInterface|string $desde, CarbonInterface|string $hasta, ?int $usuarioId = null): array
    {
        $desdeFecha = $desde instanceof CarbonInterface ? $desde->toDateString() : $desde;
        $hastaFecha = $hasta instanceof CarbonInterface ? $hasta->toDateString() : $hasta;

        $ventas = InventarioMovimiento::query()
            ->from('inventario_movimientos as movimiento')
            ->join('ventas as venta', 'venta.id', '=', 'movimiento.venta_id')
            ->where('movimiento.tipo', 'salida_venta')
            ->where('venta.estado', 'activo')
            ->whereBetween('venta.fecha_venta', [$desdeFecha, $hastaFecha])
            ->when($usuarioId !== null, fn ($query) => $query->where('venta.add_user', $usuarioId))
            ->selectRaw('COALESCE(SUM(ABS(movimiento.cantidad) * movimiento.costo_unitario), 0) as total')
            ->value('total');

        $devoluciones = InventarioMovimiento::query()
            ->from('inventario_movimientos as movimiento')
            ->join('devoluciones as devolucion', 'devolucion.id', '=', 'movimiento.devolucion_id')
            ->join('ventas as venta', 'venta.id', '=', 'devolucion.venta_id')
            ->where('movimiento.tipo', 'devolucion_venta')
            ->where('devolucion.estado', 'activo')
            ->whereBetween('devolucion.fecha', [$desdeFecha, $hastaFecha])
            ->when($usuarioId !== null, fn ($query) => $query->where('venta.add_user', $usuarioId))
            ->selectRaw('COALESCE(SUM(ABS(movimiento.cantidad) * movimiento.costo_unitario), 0) as total')
            ->value('total');

        $costoVentas = toMoney((float) $ventas, 4);
        $costoDevoluciones = toMoney((float) $devoluciones, 4);

        return [
            'ventas' => $costoVentas,
            'devoluciones' => $costoDevoluciones,
            'neto' => toMoney($costoVentas - $costoDevoluciones, 4),
        ];
    }
}
