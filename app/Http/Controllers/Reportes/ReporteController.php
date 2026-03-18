<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\CapitalCuenta;
use App\Models\CapitalMovimiento;
use App\Models\Devolucion;
use App\Models\Gasto;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\MovimientoCaja;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $desde = ! empty($validated['desde'])
            ? Carbon::parse((string) $validated['desde'])->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $hasta = ! empty($validated['hasta'])
            ? Carbon::parse((string) $validated['hasta'])->endOfDay()
            : now()->endOfDay();

        $desdeFecha = $desde->toDateString();
        $hastaFecha = $hasta->toDateString();

        $ventasBrutas = (float) Venta::query()
            ->where('estado', 'activo')
            ->whereBetween('fecha_venta', [$desdeFecha, $hastaFecha])
            ->sum('total');

        $devoluciones = (float) Devolucion::query()
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$desdeFecha, $hastaFecha])
            ->sum('total');

        $ventasNetas = toMoney($ventasBrutas - $devoluciones, 4);

        $costoVentas = (float) InventarioMovimiento::query()
            ->where('tipo', 'salida_venta')
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(ABS(cantidad) * costo_unitario), 0) as total')
            ->value('total');

        $costoDevoluciones = (float) InventarioMovimiento::query()
            ->where('tipo', 'devolucion_venta')
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(ABS(cantidad) * costo_unitario), 0) as total')
            ->value('total');

        $costoVentasNeto = toMoney($costoVentas - $costoDevoluciones, 4);
        $gananciaBruta = toMoney($ventasNetas - $costoVentasNeto, 4);

        $gastos = (float) Gasto::query()
            ->whereBetween('fecha', [$desdeFecha, $hastaFecha])
            ->sum('monto');

        $perdidasInventario = (float) InventarioMovimiento::query()
            ->where('tipo', 'ajuste_inventario')
            ->where('cantidad', '<', 0)
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(ABS(cantidad) * costo_unitario), 0) as total')
            ->value('total');

        $gananciaNeta = toMoney($gananciaBruta - $gastos - $perdidasInventario, 4);

        $flujoRows = MovimientoCaja::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->select([
                'tipo',
                DB::raw('COUNT(*) as movimientos'),
                DB::raw('COALESCE(SUM(CASE WHEN monto > 0 THEN monto ELSE 0 END), 0) as ingresos'),
                DB::raw('COALESCE(SUM(CASE WHEN monto < 0 THEN ABS(monto) ELSE 0 END), 0) as egresos'),
                DB::raw('COALESCE(SUM(monto), 0) as neto'),
            ])
            ->groupBy('tipo')
            ->orderBy('tipo')
            ->get()
            ->map(fn ($row): array => [
                'tipo' => $row->tipo,
                'movimientos' => (int) $row->movimientos,
                'ingresos' => (float) $row->ingresos,
                'egresos' => (float) $row->egresos,
                'neto' => (float) $row->neto,
            ])
            ->values();

        $flujoIngresos = (float) $flujoRows->sum('ingresos');
        $flujoEgresos = (float) $flujoRows->sum('egresos');
        $flujoNeto = toMoney($flujoIngresos - $flujoEgresos, 4);

        $productosInventario = Producto::query()
            ->with('categoria:id,nombre')
            ->where('stock_actual', '>', 0)
            ->orderByDesc('stock_actual')
            ->get([
                'id',
                'categoria_id',
                'nombre',
                'stock_actual',
                'costo_promedio',
                'activo',
            ]);

        $lotesPorProducto = InventarioLote::query()
            ->where('cantidad_disponible', '>', 0)
            ->selectRaw('producto_id, COALESCE(SUM(cantidad_disponible), 0) as stock_lotes')
            ->selectRaw('COALESCE(SUM(cantidad_disponible * costo_unitario), 0) as valor_lotes')
            ->groupBy('producto_id')
            ->get()
            ->keyBy('producto_id');

        $inventarioValorizado = $productosInventario
            ->map(function (Producto $producto) use ($lotesPorProducto): array {
                $resumenLotes = $lotesPorProducto->get($producto->id);

                $stockLotes = (float) ($resumenLotes->stock_lotes ?? 0);
                $valorLotes = (float) ($resumenLotes->valor_lotes ?? 0);

                $usaLotes = $stockLotes > 0;
                $stockValorizado = $usaLotes ? toMoney($stockLotes, 4) : toMoney((float) $producto->stock_actual, 4);
                $valor = $usaLotes
                    ? toMoney($valorLotes, 4)
                    : toMoney((float) $producto->stock_actual * (float) $producto->costo_promedio, 4);
                $costoUnitarioValorizacion = $stockValorizado > 0
                    ? toMoney($valor / $stockValorizado, 4)
                    : 0.0;

                return [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'categoria' => $producto->categoria?->nombre ?? 'Sin categoria',
                    'stock_actual' => (float) $stockValorizado,
                    'costo_promedio' => (float) $costoUnitarioValorizacion,
                    'costo_unitario_valorizacion' => (float) $costoUnitarioValorizacion,
                    'valor_total' => $valor,
                    'activo' => (bool) $producto->activo,
                    'metodo_valorizacion' => $usaLotes ? 'lotes' : 'fallback_promedio',
                ];
            })
            ->sortByDesc('valor_total')
            ->values();

        $inventarioPorCategoria = $inventarioValorizado
            ->groupBy('categoria')
            ->map(function ($items, $categoria): array {
                return [
                    'categoria' => (string) $categoria,
                    'productos' => $items->count(),
                    'valor_total' => (float) $items->sum('valor_total'),
                ];
            })
            ->sortByDesc('valor_total')
            ->values();

        $cajaPosDisponible = (float) MovimientoCaja::query()
            ->join('cajas', 'cajas.id', '=', 'movimientos_caja.caja_id')
            ->where('cajas.estado', 'abierta')
            ->sum('movimientos_caja.monto');

        $capitalCuentas = CapitalCuenta::query()
            ->whereIn('codigo', ['caja_general', 'banco'])
            ->where('activo', true)
            ->get(['codigo', 'saldo_actual'])
            ->keyBy('codigo');

        $cajaGeneral = (float) ($capitalCuentas->get('caja_general')->saldo_actual ?? 0);
        $banco = (float) ($capitalCuentas->get('banco')->saldo_actual ?? 0);
        $efectivoTotal = toMoney($cajaPosDisponible + $cajaGeneral + $banco, 4);

        $inventarioTotal = toMoney((float) $inventarioValorizado->sum('valor_total'), 4);
        $totalNegocio = toMoney($efectivoTotal + $inventarioTotal, 4);
        $inversionInicialMovimiento = CapitalMovimiento::query()
            ->where('tipo', 'ingreso_capital')
            ->orderBy('fecha')
            ->orderBy('id')
            ->first(['monto']);
        $inversionInicial = (float) toMoney((float) ($inversionInicialMovimiento?->monto ?? 0), 4);
        $resultadoVsInversion = toMoney($totalNegocio - $inversionInicial, 4);

        return response()->json([
            'data' => [
                'meta' => [
                    'desde' => $desdeFecha,
                    'hasta' => $hastaFecha,
                ],
                'utilidad' => [
                    'ventas_brutas' => (float) toMoney($ventasBrutas, 4),
                    'devoluciones' => (float) toMoney($devoluciones, 4),
                    'ventas_netas' => (float) $ventasNetas,
                    'costo_ventas' => (float) toMoney($costoVentas, 4),
                    'costo_devoluciones' => (float) toMoney($costoDevoluciones, 4),
                    'costo_ventas_neto' => (float) $costoVentasNeto,
                    'ganancia_bruta' => (float) $gananciaBruta,
                    'gastos' => (float) toMoney($gastos, 4),
                    'perdidas_inventario' => (float) toMoney($perdidasInventario, 4),
                    'ganancia_neta' => (float) $gananciaNeta,
                ],
                'flujo_caja' => [
                    'ingresos' => (float) toMoney($flujoIngresos, 4),
                    'egresos' => (float) toMoney($flujoEgresos, 4),
                    'neto' => (float) $flujoNeto,
                    'detalle' => $flujoRows,
                ],
                'inventario_valorizado' => [
                    'total' => (float) $inventarioTotal,
                    'productos' => $inventarioValorizado->count(),
                    'categorias' => $inventarioPorCategoria,
                    'top_productos' => $inventarioValorizado->take(10)->values(),
                ],
                'estado_general' => [
                    'dinero_disponible' => [
                        'caja_pos' => (float) toMoney($cajaPosDisponible, 4),
                        'caja_general' => (float) toMoney($cajaGeneral, 4),
                        'banco' => (float) toMoney($banco, 4),
                        'total_efectivo' => (float) $efectivoTotal,
                    ],
                    'inventario' => (float) $inventarioTotal,
                    'total_negocio' => (float) $totalNegocio,
                    'inversion_inicial' => (float) $inversionInicial,
                    'resultado' => (float) $resultadoVsInversion,
                ],
            ],
        ]);
    }
}