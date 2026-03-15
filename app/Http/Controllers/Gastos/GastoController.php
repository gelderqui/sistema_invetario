<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\TipoGasto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GastoController extends Controller
{
    public function index(): JsonResponse
    {
        $gastos = Gasto::query()
            ->with(['tipoGasto:id,nombre', 'usuario:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'tipo_gasto_id',
                'descripcion',
                'monto',
                'fecha',
                'usuario_id',
                'metodo_pago',
                'created_at',
            ]);

        return response()->json([
            'data' => $gastos,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $tipos = TipoGasto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);

        return response()->json([
            'data' => [
                'tipos_gasto' => $tipos,
                'metodos_pago' => ['efectivo', 'transferencia', 'tarjeta', 'mixto'],
            ],
        ]);
    }

    public function reportes(Request $request): JsonResponse
    {
        $desde = $request->filled('desde')
            ? Carbon::parse((string) $request->input('desde'))->toDateString()
            : Carbon::today()->startOfMonth()->toDateString();
        $hasta = $request->filled('hasta')
            ? Carbon::parse((string) $request->input('hasta'))->toDateString()
            : Carbon::today()->toDateString();

        $porDia = Gasto::query()
            ->select([
                DB::raw('fecha as fecha'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto) as total'),
            ])
            ->whereBetween('fecha', [$desde, $hasta])
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        $porTipo = Gasto::query()
            ->join('tipos_gasto', 'tipos_gasto.id', '=', 'gastos.tipo_gasto_id')
            ->select([
                'tipos_gasto.id as tipo_gasto_id',
                'tipos_gasto.nombre as tipo_nombre',
                DB::raw('COUNT(gastos.id) as cantidad'),
                DB::raw('SUM(gastos.monto) as total'),
            ])
            ->whereBetween('gastos.fecha', [$desde, $hasta])
            ->groupBy('tipos_gasto.id', 'tipos_gasto.nombre')
            ->orderByDesc('total')
            ->get();

        $totalPeriodo = (float) Gasto::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        return response()->json([
            'data' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'total_periodo' => toMoney($totalPeriodo, 4),
                'por_dia' => $porDia,
                'por_tipo' => $porTipo,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_gasto_id' => ['required', Rule::exists('tipos_gasto', 'id')],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'transferencia', 'tarjeta', 'mixto'])],
        ]);

        $gasto = Gasto::query()->create([
            ...$validated,
            'monto' => toMoney($validated['monto'], 4),
            'usuario_id' => $request->user()->id,
        ]);

        if ($gasto->metodo_pago === 'efectivo') {
            registrarMovimientoCajaAutomatico(
                (int) $request->user()->id,
                'gasto',
                -1 * (float) $gasto->monto,
                'Salida por gasto: '.$gasto->descripcion,
                $gasto->fecha?->toDateString(),
                'gasto',
                $gasto->id
            );
        }

        $gasto->load(['tipoGasto:id,nombre', 'usuario:id,name']);

        return response()->json([
            'message' => 'Gasto registrado correctamente.',
            'data' => $gasto,
        ], 201);
    }
}
