<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Devolucion;
use App\Models\Gasto;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\Reportes\CostoVentasService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CostoVentasService $costos): JsonResponse
    {
        $user = $request->user()?->loadMissing('role');
        $isAdmin = $user?->role?->code === 'admin';

        $hoy = Carbon::today();
        $inicioMes = Carbon::today()->startOfMonth();
        $finMes = Carbon::today()->endOfMonth();

        $ventasQuery = Venta::query()->where('estado', 'activo');
        $comprasQuery = Compra::query()->where('estado', 'activo')->where('es_consignacion', false);
        $gastosQuery = Gasto::query();

        if (! $isAdmin && $user) {
            $ventasQuery->where('add_user', $user->id);
            $comprasQuery->where('add_user', $user->id);
            $gastosQuery->where('usuario_id', $user->id);
        }

        $ventasHoy = (float) (clone $ventasQuery)->whereDate('fecha_venta', $hoy)->sum('total');
        $comprasHoy = (float) (clone $comprasQuery)->whereDate('fecha_compra', $hoy)->sum('total');
        $gastosHoy = (float) (clone $gastosQuery)->whereDate('fecha', $hoy)->sum('monto');
        $usuarioId = ! $isAdmin && $user ? (int) $user->id : null;
        $resumenCostosHoy = $costos->resumen($hoy, $hoy, $usuarioId);
        $costoVentasNetoHoy = $resumenCostosHoy['neto'];

        $devolucionesHoy = (float) Devolucion::query()
            ->from('devoluciones as devolucion')
            ->join('ventas as venta', 'venta.id', '=', 'devolucion.venta_id')
            ->where('devolucion.estado', 'activo')
            ->whereDate('devolucion.fecha', $hoy)
            ->when($usuarioId !== null, fn ($query) => $query->where('venta.add_user', $usuarioId))
            ->sum('devolucion.total');
        $ventasNetasHoy = toMoney($ventasHoy - $devolucionesHoy, 4);

        $ventasDiaCount = (int) (clone $ventasQuery)->whereDate('fecha_venta', $hoy)->count();
        $ventasMesTotal = (float) (clone $ventasQuery)
            ->whereBetween('fecha_venta', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->sum('total');

        $ticketPromedio = $ventasDiaCount > 0
            ? toMoney($ventasHoy / $ventasDiaCount, 4)
            : 0.0;

        $productosQuery = Producto::query()->where('activo', true);

        if (! $isAdmin && $user) {
            $productoIds = InventarioMovimiento::query()
                ->where('add_user', $user->id)
                ->distinct()
                ->pluck('producto_id');

            if ($productoIds->isEmpty()) {
                $bajoStock = 0;
                $porVencer = 0;

                return response()->json([
                    'data' => [
                        'scope' => 'user',
                        'hoy' => [
                            'ventas' => toMoney($ventasHoy, 4),
                            'compras' => toMoney($comprasHoy, 4),
                            'gastos' => toMoney($gastosHoy, 4),
                            'ganancia_estimada' => toMoney($ventasNetasHoy - $costoVentasNetoHoy - $gastosHoy, 4),
                        ],
                        'productos' => [
                            'bajo_stock' => $bajoStock,
                            'por_vencer' => $porVencer,
                        ],
                        'ventas' => [
                            'del_dia' => $ventasDiaCount,
                            'del_mes_total' => toMoney($ventasMesTotal, 4),
                            'ticket_promedio' => toMoney($ticketPromedio, 4),
                        ],
                    ],
                ]);
            }

            $productosQuery->whereIn('id', $productoIds);
        }

        $bajoStock = (int) (clone $productosQuery)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();

        $porVencer = (int) (clone $productosQuery)
            ->where('control_vencimiento', true)
            ->whereHas('inventarioLotes', function ($query): void {
                $query->where('cantidad_disponible', '>', 0)
                    ->whereNotNull('fecha_vencimiento')
                    ->whereRaw('fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL productos.dias_alerta_vencimiento DAY)');
            })
            ->count();

        $gananciaEstimadaHoy = toMoney($ventasNetasHoy - $costoVentasNetoHoy - $gastosHoy, 4);

        return response()->json([
            'data' => [
                'scope' => $isAdmin ? 'global' : 'user',
                'hoy' => [
                    'ventas' => toMoney($ventasHoy, 4),
                    'compras' => toMoney($comprasHoy, 4),
                    'gastos' => toMoney($gastosHoy, 4),
                    'ganancia_estimada' => $gananciaEstimadaHoy,
                ],
                'productos' => [
                    'bajo_stock' => $bajoStock,
                    'por_vencer' => $porVencer,
                ],
                'ventas' => [
                    'del_dia' => $ventasDiaCount,
                    'del_mes_total' => toMoney($ventasMesTotal, 4),
                    'ticket_promedio' => toMoney($ticketPromedio, 4),
                ],
            ],
        ]);
    }
}
