<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function gastos(Request $request): JsonResponse
    {
        $desde = $request->filled('desde')
            ? Carbon::parse((string) $request->input('desde'))->toDateString()
            : Carbon::today()->startOfMonth()->toDateString();
        $hasta = $request->filled('hasta')
            ? Carbon::parse((string) $request->input('hasta'))->toDateString()
            : Carbon::today()->toDateString();

        $user = $request->user();
        $user->loadMissing('role:id,code');
        $isAdmin = $user->role?->code === 'admin';

        $base = Gasto::query()->whereBetween('fecha', [$desde, $hasta]);

        if (! $isAdmin) {
            $base->where('usuario_id', $user->id);
        }

        $porDia = (clone $base)
            ->select([
                DB::raw('fecha as fecha'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(monto) as total'),
            ])
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        $porTipo = (clone $base)
            ->join('tipos_gasto', 'tipos_gasto.id', '=', 'gastos.tipo_gasto_id')
            ->select([
                'tipos_gasto.id as tipo_gasto_id',
                'tipos_gasto.nombre as tipo_nombre',
                DB::raw('COUNT(gastos.id) as cantidad'),
                DB::raw('SUM(gastos.monto) as total'),
            ])
            ->groupBy('tipos_gasto.id', 'tipos_gasto.nombre')
            ->orderByDesc('total')
            ->get();

        $totalPeriodo = (float) (clone $base)->sum('monto');

        return response()->json([
            'data' => [
                'scope' => $isAdmin ? 'global' : 'usuario',
                'desde' => $desde,
                'hasta' => $hasta,
                'total_periodo' => toMoney($totalPeriodo, 4),
                'por_dia' => $porDia,
                'por_tipo' => $porTipo,
            ],
        ]);
    }
}
