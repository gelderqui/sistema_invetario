<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\ArqueoCaja;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\Gasto;
use App\Models\MovimientoCaja;
use App\Models\TipoGasto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    public function catalogs(): JsonResponse
    {
        $tiposGasto = TipoGasto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json([
            'data' => [
                'tipos_gasto' => $tiposGasto,
                'destinos_egreso' => ['banco', 'caja_general', 'otro'],
            ],
        ]);
    }

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
        $maxAperturasPorDia = max(1, (int) Configuracion::valor('caja_aperturas_maximas_por_dia', 1));

        $yaAbierta = Caja::query()
            ->where('usuario_id', $user->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($yaAbierta) {
            return response()->json([
                'message' => 'Ya existe una caja abierta para este usuario.',
            ], 422);
        }

        $aperturasEnFecha = Caja::query()
            ->where('usuario_id', $user->id)
            ->whereDate('fecha_apertura', $fechaApertura)
            ->count();

        if ($aperturasEnFecha >= $maxAperturasPorDia) {
            return response()->json([
                'message' => 'Se alcanzo el limite de '.$maxAperturasPorDia.' aperturas de caja por usuario para este dia.',
            ], 422);
        }

        $caja = DB::transaction(function () use ($validated, $user, $fechaApertura, $maxAperturasPorDia) {
            $fecha = $validated['fecha_apertura'] ?? now()->toDateTimeString();
            $montoApertura = toMoney($validated['monto_apertura'], 4);
            $cajaGeneral = capitalCuentaPorCodigo('caja_general');

            $yaAbiertaTransaccion = Caja::query()
                ->lockForUpdate()
                ->where('usuario_id', $user->id)
                ->where('estado', 'abierta')
                ->exists();

            if ($yaAbiertaTransaccion) {
                throw ValidationException::withMessages([
                    'monto_apertura' => ['Ya existe una caja abierta para este usuario.'],
                ]);
            }

            $aperturasEnFechaTransaccion = Caja::query()
                ->lockForUpdate()
                ->where('usuario_id', $user->id)
                ->whereDate('fecha_apertura', $fechaApertura)
                ->count();

            if ($aperturasEnFechaTransaccion >= $maxAperturasPorDia) {
                throw ValidationException::withMessages([
                    'monto_apertura' => ['Se alcanzo el limite de '.$maxAperturasPorDia.' aperturas para este dia.'],
                ]);
            }

            if (! $cajaGeneral) {
                throw ValidationException::withMessages([
                    'monto_apertura' => ['No existe una cuenta activa de caja general para fondear la apertura.'],
                ]);
            }

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

            if ($montoApertura > 0) {
                registrarMovimientoCapital(
                    tipo: 'apertura_caja',
                    monto: $montoApertura,
                    usuarioId: (int) $user->id,
                    cuentaOrigenId: $cajaGeneral->id,
                    cuentaDestinoId: null,
                    descripcion: 'Salida de caja general para apertura de caja POS #'.$caja->id,
                    fecha: $fecha,
                    referenciaTipo: 'caja',
                    referenciaId: $caja->id,
                    meta: [
                        'caja_id' => $caja->id,
                        'usuario_caja_id' => $user->id,
                    ]
                );
            }

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
            'tipo' => ['required', 'in:ingreso,egreso,gasto'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'destino' => ['nullable', 'string', 'max:80'],
            'tipo_gasto_id' => ['nullable', 'integer', 'exists:tipos_gasto,id'],
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

        $movimiento = DB::transaction(function () use ($validated, $caja, $user) {
            $tipo = $validated['tipo'];
            $fecha = $validated['fecha'] ?? now()->toDateTimeString();
            $monto = toMoney($validated['monto'], 4);
            $descripcion = trim((string) ($validated['descripcion'] ?? ''));

            if (in_array($tipo, ['egreso', 'gasto'], true)) {
                $monto *= -1;
            }

            if ($tipo === 'gasto') {
                if (! isset($validated['tipo_gasto_id'])) {
                    throw ValidationException::withMessages([
                        'tipo_gasto_id' => ['Debe seleccionar tipo de gasto para registrar gasto desde caja.'],
                    ]);
                }

                $gasto = Gasto::query()->create([
                    'tipo_gasto_id' => (int) $validated['tipo_gasto_id'],
                    'descripcion' => $descripcion !== '' ? $descripcion : 'Gasto registrado desde caja POS',
                    'monto' => toMoney($validated['monto'], 4),
                    'fecha' => Carbon::parse((string) $fecha)->toDateString(),
                    'usuario_id' => $user->id,
                    'metodo_pago' => 'caja',
                ]);

                return MovimientoCaja::query()->create([
                    'caja_id' => $caja->id,
                    'tipo' => 'gasto',
                    'monto' => $monto,
                    'descripcion' => $gasto->descripcion,
                    'referencia_tipo' => 'gasto',
                    'referencia_id' => $gasto->id,
                    'fecha' => $fecha,
                    'usuario_id' => $user->id,
                ]);
            }

            if ($tipo === 'egreso' && ! empty($validated['destino'])) {
                $destino = str_replace('_', ' ', (string) $validated['destino']);
                $descripcion = trim($descripcion.' | Destino: '.$destino);
            }

            $movimiento = MovimientoCaja::query()->create([
                'caja_id' => $caja->id,
                'tipo' => $tipo,
                'monto' => $monto,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'fecha' => $fecha,
                'usuario_id' => $user->id,
            ]);

            if ($tipo === 'egreso' && in_array(($validated['destino'] ?? null), ['banco', 'caja_general'], true)) {
                $cuentaDestino = capitalCuentaPorCodigo((string) $validated['destino']);

                if (! $cuentaDestino) {
                    throw ValidationException::withMessages([
                        'destino' => ['No existe una cuenta activa para el destino seleccionado.'],
                    ]);
                }

                registrarMovimientoCapital(
                    tipo: 'egreso_caja',
                    monto: $validated['monto'],
                    usuarioId: (int) $user->id,
                    cuentaOrigenId: null,
                    cuentaDestinoId: $cuentaDestino->id,
                    descripcion: 'Ingreso desde caja POS #'.$caja->id.' hacia '.$cuentaDestino->nombre,
                    fecha: $fecha,
                    referenciaTipo: 'movimiento_caja',
                    referenciaId: $movimiento->id,
                    meta: [
                        'caja_id' => $caja->id,
                        'destino' => $validated['destino'],
                    ]
                );
            }

            return $movimiento;
        });

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

        return response()->json([
            'message' => 'Arqueo registrado correctamente.',
            'data' => [
                'arqueo' => $arqueo,
                'resumen' => $this->resumenCaja($caja->id),
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

        if ($ultimoArqueo) {
            $montoContadoFinal = toMoney($ultimoArqueo->monto_contado, 4);
        } elseif (array_key_exists('monto_contado', $validated)) {
            $montoContadoFinal = toMoney($validated['monto_contado'], 4);
        } else {
            $montoContadoFinal = $resumen['monto_sistema'];
        }

        $diferencia = toMoney($montoContadoFinal - $resumen['monto_sistema'], 4);
        $alerta = $this->resolverAlertaDiferencia($diferencia);

        if ($alerta !== null) {
            throw ValidationException::withMessages([
                'monto_contado' => [$alerta['mensaje'].' Debe regularizar caja antes del cierre.'],
            ]);
        }

        DB::transaction(function () use ($validated, $resumen, $montoContadoFinal, $diferencia, $caja, $user) {
            $fechaCierre = $validated['fecha_cierre'] ?? now()->toDateTimeString();
            $cajaGeneral = capitalCuentaPorCodigo('caja_general');

            if (! $cajaGeneral) {
                throw ValidationException::withMessages([
                    'fecha_cierre' => ['No existe una cuenta activa de caja general para recibir el cierre.'],
                ]);
            }

            if ($montoContadoFinal > 0) {
                registrarMovimientoCapital(
                    tipo: 'cierre_caja',
                    monto: $montoContadoFinal,
                    usuarioId: (int) $user->id,
                    cuentaOrigenId: null,
                    cuentaDestinoId: $cajaGeneral->id,
                    descripcion: 'Ingreso a caja general por cierre de caja POS #'.$caja->id,
                    fecha: $fechaCierre,
                    referenciaTipo: 'caja',
                    referenciaId: $caja->id,
                    meta: [
                        'caja_id' => $caja->id,
                        'monto_sistema' => $resumen['monto_sistema'],
                        'diferencia' => $diferencia,
                    ]
                );
            }

            $caja->update([
                'fecha_cierre' => $fechaCierre,
                'total_ventas' => $resumen['total_ventas'],
                'total_gastos' => $resumen['total_gastos'],
                'total_compras' => $resumen['total_egresos'],
                'total_ajustes' => $resumen['total_ingresos'],
                'monto_sistema_final' => $resumen['monto_sistema'],
                'monto_contado_final' => $montoContadoFinal,
                'diferencia' => $diferencia,
                'estado' => 'cerrada',
            ]);
        });

        return response()->json([
            'message' => 'Caja cerrada correctamente.',
            'data' => [
                'caja' => $caja->fresh(),
                'alerta' => $alerta,
            ],
        ]);
    }

    private function resolverMontoContado(array $validated): float
    {
        if (! empty($validated['billetes'])) {
            $total = collect($validated['billetes'])
                ->sum(fn (array $row): float => toMoney((float) $row['denominacion'] * (float) $row['cantidad'], 4));

            if ($total > 0) {
                return toMoney($total, 4);
            }
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
        $totalIngresos = toMoney((float) (clone $base)->where('tipo', 'ingreso')->sum('monto'), 4);
        $totalEgresos = toMoney((float) abs((float) (clone $base)->where('tipo', 'egreso')->sum('monto')), 4);
        $montoSistema = $this->calcularMontoSistema($cajaId);

        return [
            'total_apertura' => $totalApertura,
            'total_ventas' => $totalVentas,
            'total_gastos' => $totalGastos,
            'total_ingresos' => $totalIngresos,
            'total_egresos' => $totalEgresos,
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
