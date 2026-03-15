<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\ArqueoCaja;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\MovimientoCaja;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    public function estado(Request $request): JsonResponse
    {
        $user = $request->user();

        $cajaActiva = Caja::query()
            ->with(['usuario:id,name', 'arqueos' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();

        $resumen = null;

        if ($cajaActiva) {
            $resumen = $this->resumenCaja($cajaActiva->id);
        }

        return response()->json([
            'data' => [
                'caja_activa' => $cajaActiva,
                'resumen' => $resumen,
            ],
        ]);
    }

    public function movimientos(Request $request): JsonResponse
    {
        $user = $request->user();

        $cajaId = $request->integer('caja_id');
        $caja = $cajaId
            ? Caja::query()->where('id', $cajaId)->where('usuario_id', $user->id)->first()
            : Caja::query()->where('usuario_id', $user->id)->where('estado', 'abierta')->latest('id')->first();

        if (! $caja) {
            return response()->json([
                'data' => [
                    'caja' => null,
                    'movimientos' => [],
                ],
            ]);
        }

        $movimientos = MovimientoCaja::query()
            ->where('caja_id', $caja->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'caja_id',
                'tipo',
                'monto',
                'descripcion',
                'referencia_tipo',
                'referencia_id',
                'fecha',
                'usuario_id',
            ]);

        return response()->json([
            'data' => [
                'caja' => $caja,
                'movimientos' => $movimientos,
                'resumen' => $this->resumenCaja($caja->id),
            ],
        ]);
    }

    public function apertura(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monto_apertura' => ['required', 'numeric', 'gte:0'],
            'fecha_apertura' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $fechaApertura = Carbon::parse((string) ($validated['fecha_apertura'] ?? now()))->toDateString();

        $yaAbierta = Caja::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($yaAbierta) {
            return response()->json([
                'message' => 'Ya existe una caja abierta para este usuario.',
            ], 422);
        }

        $yaTieneCajaEnFecha = Caja::query()
            ->where('usuario_id', $user->id)
            ->whereDate('fecha_apertura', $fechaApertura)
            ->exists();

        if ($yaTieneCajaEnFecha) {
            return response()->json([
                'message' => 'Solo se permite una apertura de caja por usuario por dia.',
            ], 422);
        }

        $caja = DB::transaction(function () use ($validated, $user) {
            $fecha = $validated['fecha_apertura'] ?? now()->toDateTimeString();
            $montoApertura = toMoney($validated['monto_apertura'], 4);

            $caja = Caja::query()->create([
                'usuario_id' => $user->id,
                'fecha_apertura' => $fecha,
                'monto_apertura' => $montoApertura,
                'estado' => 'abierta',
            ]);

            MovimientoCaja::query()->create([
                'caja_id' => $caja->id,
                'tipo' => 'apertura',
                'monto' => $montoApertura,
                'descripcion' => 'Apertura de caja',
                'fecha' => $fecha,
                'usuario_id' => $user->id,
            ]);

            return $caja;
        });

        return response()->json([
            'message' => 'Caja abierta correctamente.',
            'data' => [
                'caja' => $caja,
                'resumen' => $this->resumenCaja($caja->id),
            ],
        ], 201);
    }

    public function registrarAjuste(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', 'in:ingreso_manual,ajuste'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'fecha' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        $caja = Caja::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();

        if (! $caja) {
            return response()->json([
                'message' => 'No hay caja abierta para registrar movimientos.',
            ], 422);
        }

        $monto = toMoney($validated['monto'], 4);
        if ($validated['tipo'] === 'ajuste') {
            $monto *= -1;
        }

        $movimiento = MovimientoCaja::query()->create([
            'caja_id' => $caja->id,
            'tipo' => $validated['tipo'],
            'monto' => $monto,
            'descripcion' => $validated['descripcion'] ?? null,
            'fecha' => $validated['fecha'] ?? now()->toDateTimeString(),
            'usuario_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Movimiento de caja registrado.',
            'data' => [
                'movimiento' => $movimiento,
                'resumen' => $this->resumenCaja($caja->id),
            ],
        ], 201);
    }

    public function arqueo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monto_contado' => ['nullable', 'numeric', 'gte:0'],
            'billetes' => ['nullable', 'array'],
            'billetes.*.denominacion' => ['required_with:billetes', 'numeric', 'gt:0'],
            'billetes.*.cantidad' => ['required_with:billetes', 'integer', 'gte:0'],
            'fecha' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        $caja = Caja::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();

        if (! $caja) {
            return response()->json([
                'message' => 'No hay caja abierta para arqueo.',
            ], 422);
        }

        $montoSistema = $this->calcularMontoSistema($caja->id);
        $montoContado = $this->resolverMontoContado($validated);
        $diferencia = toMoney($montoContado - $montoSistema, 4);

        $arqueo = ArqueoCaja::query()->create([
            'caja_id' => $caja->id,
            'monto_sistema' => $montoSistema,
            'monto_contado' => $montoContado,
            'diferencia' => $diferencia,
            'detalle_billetes' => $validated['billetes'] ?? null,
            'usuario_id' => $user->id,
            'fecha' => $validated['fecha'] ?? now()->toDateTimeString(),
        ]);

        $alerta = $this->resolverAlertaDiferencia($diferencia);

        return response()->json([
            'message' => 'Arqueo registrado correctamente.',
            'data' => [
                'arqueo' => $arqueo,
                'resumen' => $this->resumenCaja($caja->id),
                'alerta' => $alerta,
            ],
        ], 201);
    }

    public function cierre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monto_contado' => ['nullable', 'numeric', 'gte:0'],
            'fecha_cierre' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        $caja = Caja::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();

        if (! $caja) {
            return response()->json([
                'message' => 'No hay caja abierta para cerrar.',
            ], 422);
        }

        $resumen = $this->resumenCaja($caja->id);

        $ultimoArqueo = ArqueoCaja::query()
            ->where('caja_id', $caja->id)
            ->latest('id')
            ->first();

        $montoContadoFinal = array_key_exists('monto_contado', $validated)
            ? toMoney($validated['monto_contado'], 4)
            : ($ultimoArqueo ? toMoney($ultimoArqueo->monto_contado, 4) : $resumen['monto_sistema']);

        $diferencia = toMoney($montoContadoFinal - $resumen['monto_sistema'], 4);
        $alerta = $this->resolverAlertaDiferencia($diferencia);

        $caja->update([
            'fecha_cierre' => $validated['fecha_cierre'] ?? now()->toDateTimeString(),
            'total_ventas' => $resumen['total_ventas'],
            'total_gastos' => $resumen['total_gastos'],
            'total_compras' => $resumen['total_compras'],
            'total_ajustes' => $resumen['total_ajustes'],
            'monto_sistema_final' => $resumen['monto_sistema'],
            'monto_contado_final' => $montoContadoFinal,
            'diferencia' => $diferencia,
            'estado' => 'cerrada',
        ]);

        return response()->json([
            'message' => 'Caja cerrada correctamente.',
            'data' => [
                'caja' => $caja->fresh(),
                'alerta' => $alerta,
            ],
        ]);
    }

    public function reporteDia(Request $request): JsonResponse
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse((string) $request->input('fecha'))->toDateString()
            : now()->toDateString();

        $query = Caja::query()
            ->with('usuario:id,name')
            ->whereDate('fecha_apertura', $fecha)
            ->orderBy('fecha_apertura');

        $user = $request->user();
        $user->loadMissing('role:id,code');

        if ($user->role?->code !== 'admin') {
            $query->where('usuario_id', $user->id);
        }

        $cajas = $query->get();

        $data = $cajas->map(function (Caja $caja): array {
            $resumen = $this->resumenCaja($caja->id);

            return [
                'id' => $caja->id,
                'usuario' => $caja->usuario?->name,
                'estado' => $caja->estado,
                'fecha_apertura' => $caja->fecha_apertura,
                'fecha_cierre' => $caja->fecha_cierre,
                ...$resumen,
                'monto_contado_final' => $caja->monto_contado_final,
                'diferencia_final' => $caja->diferencia,
            ];
        })->values();

        return response()->json([
            'data' => [
                'fecha' => $fecha,
                'items' => $data,
            ],
        ]);
    }

    private function resolverMontoContado(array $validated): float
    {
        if (! empty($validated['billetes'])) {
            $total = collect($validated['billetes'])
                ->sum(fn (array $row): float => toMoney((float) $row['denominacion'] * (float) $row['cantidad'], 4));

            return toMoney($total, 4);
        }

        return toMoney($validated['monto_contado'] ?? 0, 4);
    }

    private function calcularMontoSistema(int $cajaId): float
    {
        return toMoney((float) MovimientoCaja::query()->where('caja_id', $cajaId)->sum('monto'), 4);
    }

    private function resumenCaja(int $cajaId): array
    {
        $base = MovimientoCaja::query()->where('caja_id', $cajaId);

        $totalApertura = toMoney((float) (clone $base)->where('tipo', 'apertura')->sum('monto'), 4);
        $totalVentas = toMoney((float) (clone $base)->where('tipo', 'venta')->sum('monto'), 4);
        $totalGastos = toMoney((float) abs((float) (clone $base)->where('tipo', 'gasto')->sum('monto')), 4);
        $totalCompras = toMoney((float) abs((float) (clone $base)->where('tipo', 'compra')->sum('monto')), 4);
        $totalAjustes = toMoney((float) (clone $base)->whereIn('tipo', ['ingreso_manual', 'ajuste'])->sum('monto'), 4);
        $montoSistema = $this->calcularMontoSistema($cajaId);

        return [
            'total_apertura' => $totalApertura,
            'total_ventas' => $totalVentas,
            'total_gastos' => $totalGastos,
            'total_compras' => $totalCompras,
            'total_ajustes' => $totalAjustes,
            'monto_sistema' => $montoSistema,
        ];
    }

    private function resolverAlertaDiferencia(float $diferencia): ?array
    {
        if ($diferencia >= 0) {
            return null;
        }

        $umbral = (float) Configuracion::valor('caja_alerta_faltante_monto', 50);
        $faltante = abs($diferencia);

        if ($faltante < $umbral) {
            return null;
        }

        return [
            'tipo' => 'faltante_alto',
            'umbral' => toMoney($umbral, 4),
            'monto' => toMoney($faltante, 4),
            'mensaje' => sprintf('Faltante alto detectado: Q %.2f (umbral Q %.2f).', $faltante, $umbral),
        ];
    }
}
