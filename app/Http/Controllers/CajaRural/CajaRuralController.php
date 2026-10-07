<?php

namespace App\Http\Controllers\CajaRural;

use App\Http\Controllers\Controller;
use App\Models\CajaRuralArqueoCajaChica;
use App\Models\CajaRuralCuadre;
use App\Models\CajaRuralDeuda;
use App\Models\CajaRuralDeudaMovimiento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CajaRuralController extends Controller
{
    public function index(): JsonResponse
    {
        $deudas = CajaRuralDeuda::query()
            ->with('usuario:id,name')
            ->withMax([
                'movimientos as ultima_actualizacion' => fn ($query) => $query->where('tipo', '!=', 'cargo'),
            ], 'fecha')
            ->orderByRaw('COALESCE(ultima_actualizacion, fecha_origen) DESC')
            ->orderByDesc('id')
            ->get();

        $arqueos = CajaRuralArqueoCajaChica::query()
            ->with(['usuario:id,name', 'detalles'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $cuadres = CajaRuralCuadre::query()
            ->with(['usuario:id,name', 'arqueoCajaChica:id,monto_contado,fecha'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $ultimoCuadre = $cuadres->first();
        $ultimoArqueo = $arqueos->first();
        $capital = toMoney($ultimoCuadre?->capital ?? 0, 4);
        $banco = toMoney($ultimoCuadre?->banco ?? 0, 4);
        $caja = toMoney($ultimoCuadre?->caja ?? 0, 4);
        $cuentasPorPagarTotal = $this->totalCuenta('cuenta_por_pagar');
        $cuentasPorCobrarTotal = $this->totalCuenta('cuenta_por_cobrar');
        // La tarjeta representa el último cuadre confirmado, no un arqueo pendiente.
        $cajaChica = toMoney($ultimoCuadre?->caja_chica ?? 0, 4);
        $totalFinanciado = toMoney($capital + $cuentasPorPagarTotal, 4);
        $totalUbicado = toMoney($banco + $caja + $cajaChica + $cuentasPorCobrarTotal, 4);

        return response()->json([
            'data' => [
                'deudas' => $deudas,
                'arqueos' => $arqueos,
                'cuadres' => $cuadres,
                'resumen' => [
                    'capital' => $capital,
                    'banco' => $banco,
                    'caja' => $caja,
                    'cuentas_por_pagar_total' => $cuentasPorPagarTotal,
                    'cuentas_por_cobrar_total' => $cuentasPorCobrarTotal,
                    'caja_chica_total' => $cajaChica,
                    'total_financiado' => $totalFinanciado,
                    'total_ubicado' => $totalUbicado,
                    'diferencia' => toMoney($totalUbicado - $totalFinanciado, 4),
                    'ultimo_arqueo_id' => $ultimoArqueo?->id,
                    'ultimo_arqueo_monto' => toMoney($ultimoArqueo?->monto_contado ?? 0, 4),
                ],
            ],
        ]);
    }

    public function historialCampo(string $campo): JsonResponse
    {
        $camposPermitidos = [
            'capital',
            'banco',
            'caja',
            'caja_chica',
        ];

        abort_unless(in_array($campo, $camposPermitidos, true), 404);

        $cuadres = CajaRuralCuadre::query()
            ->with('usuario:id,name')
            ->when($campo === 'caja_chica', fn ($query) => $query->whereNotNull('caja_rural_arqueo_caja_chica_id'))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', $campo, 'fecha', 'usuario_id', 'caja_rural_arqueo_caja_chica_id']);

        $valorAnterior = null;
        $historial = $cuadres
            ->filter(function (CajaRuralCuadre $cuadre) use (&$valorAnterior, $campo): bool {
                $valorActual = toMoney($cuadre->{$campo}, 4);
                $mostrar = $campo === 'caja_chica' || $valorAnterior === null || $valorActual !== $valorAnterior;
                $valorAnterior = $valorActual;

                return $mostrar;
            })
            ->map(fn (CajaRuralCuadre $cuadre) => [
                'id' => $cuadre->id,
                'valor' => $cuadre->{$campo},
                'fecha' => $cuadre->fecha,
                'usuario' => $cuadre->usuario?->name,
            ])
            ->values()
            ->reverse()
            ->values();

        return response()->json(['data' => $historial]);
    }

    public function cuadres(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);
        $perPage = min(max((int) $request->integer('per_page', 5), 5), 50);
        $paginador = CajaRuralCuadre::query()
            ->with(['usuario:id,name', 'arqueoCajaChica:id,monto_contado,fecha'])
            ->when($validated['fecha_inicio'] ?? null, fn ($query, $fechaInicio) => $query->whereDate('fecha', '>=', $fechaInicio))
            ->when($validated['fecha_fin'] ?? null, fn ($query, $fechaFin) => $query->whereDate('fecha', '<=', $fechaFin))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginador->items(),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'per_page' => $paginador->perPage(),
                'total' => $paginador->total(),
            ],
        ]);
    }

    public function showCuadre(CajaRuralCuadre $cuadre): JsonResponse
    {
        $cuadre->load([
            'usuario:id,name',
            'arqueoCajaChica' => fn ($query) => $query->with(['usuario:id,name', 'detalles']),
        ]);

        return response()->json(['data' => $cuadre]);
    }

    public function storeDeuda(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['cuenta_por_pagar', 'cuenta_por_cobrar'])],
            'nombre_referencia' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha_origen' => ['nullable', 'date'],
        ]);

        $deuda = DB::transaction(function () use ($validated, $request): CajaRuralDeuda {
            $monto = toMoney($validated['monto'], 4);
            $fechaOrigen = Carbon::parse((string) ($validated['fecha_origen'] ?? now()));
            $deuda = CajaRuralDeuda::query()->create([
                'tipo' => $validated['tipo'],
                'nombre_referencia' => $validated['nombre_referencia'],
                'descripcion' => $validated['descripcion'] ?? null,
                'monto_original' => $monto,
                'saldo_pendiente' => $monto,
                'fecha_origen' => $fechaOrigen,
                'estado' => 'activa',
                'usuario_id' => $request->user()->id,
            ]);

            CajaRuralDeudaMovimiento::query()->create([
                'caja_rural_deuda_id' => $deuda->id,
                'tipo' => 'cargo',
                'monto' => $monto,
                'saldo_anterior' => 0,
                'saldo_posterior' => $monto,
                'descripcion' => 'Registro inicial de cuenta.',
                'fecha' => $fechaOrigen,
                'usuario_id' => $request->user()->id,
            ]);

            return $deuda;
        });

        return response()->json([
            'message' => 'Cuenta registrada correctamente.',
            'data' => $deuda->load('usuario:id,name'),
        ], 201);
    }

    public function updateDeuda(Request $request, CajaRuralDeuda $deuda): JsonResponse
    {
        $validated = $request->validate([
            'nombre_referencia' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'fecha_origen' => ['required', 'date'],
        ]);

        $deuda->update($validated);

        return response()->json([
            'message' => 'Datos de cuenta actualizados.',
            'data' => $deuda->fresh()->load('usuario:id,name'),
        ]);
    }

    public function movimientosDeuda(CajaRuralDeuda $deuda): JsonResponse
    {
        $movimientos = $deuda->movimientos()
            ->with('usuario:id,name')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'deuda' => $deuda,
                'movimientos' => $movimientos,
            ],
        ]);
    }

    public function registrarMovimientoDeuda(Request $request, CajaRuralDeuda $deuda): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['abono', 'ajuste_aumenta', 'ajuste_disminuye', 'anulacion'])],
            'monto' => ['nullable', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha' => ['nullable', 'date'],
        ]);

        $deuda = DB::transaction(function () use ($validated, $request, $deuda): CajaRuralDeuda {
            $deuda = CajaRuralDeuda::query()->lockForUpdate()->findOrFail($deuda->id);

            if ($deuda->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'deuda' => ['Solo se pueden registrar movimientos sobre cuentas activas.'],
                ]);
            }

            $saldoAnterior = toMoney($deuda->saldo_pendiente, 4);
            $tipo = $validated['tipo'];
            $monto = $tipo === 'anulacion'
                ? $saldoAnterior
                : toMoney($validated['monto'] ?? 0, 4);

            if ($monto <= 0) {
                throw ValidationException::withMessages([
                    'monto' => ['El monto debe ser mayor que cero.'],
                ]);
            }

            $reduce = in_array($tipo, ['abono', 'ajuste_disminuye', 'anulacion'], true);
            $saldoPosterior = $reduce ? $saldoAnterior - $monto : $saldoAnterior + $monto;

            if ($saldoPosterior < 0) {
                throw ValidationException::withMessages([
                    'monto' => ['El monto no puede superar el saldo pendiente.'],
                ]);
            }

            $estado = $tipo === 'anulacion' ? 'anulada' : ($saldoPosterior === 0.0 ? 'pagada' : 'activa');

            $deuda->update([
                'saldo_pendiente' => $saldoPosterior,
                'estado' => $estado,
            ]);

            CajaRuralDeudaMovimiento::query()->create([
                'caja_rural_deuda_id' => $deuda->id,
                'tipo' => $tipo,
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'descripcion' => $validated['descripcion'],
                'fecha' => Carbon::parse((string) ($validated['fecha'] ?? now())),
                'usuario_id' => $request->user()->id,
            ]);

            return $deuda;
        });

        return response()->json([
            'message' => 'Movimiento de cuenta registrado.',
            'data' => $deuda->fresh()->load('usuario:id,name'),
        ]);
    }

    public function storeArqueo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monto_sistema' => ['nullable', 'numeric', 'min:0'],
            'fecha' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.tipo' => ['required', Rule::in(['billete', 'moneda', 'paquete'])],
            'detalles.*.denominacion' => ['required', 'numeric', 'gt:0'],
            'detalles.*.unidades_por_paquete' => ['required', 'integer', 'min:1'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0'],
        ]);

        $arqueo = DB::transaction(function () use ($validated, $request): CajaRuralArqueoCajaChica {
            $detalles = collect($validated['detalles'])->map(function (array $detalle): array {
                $subtotal = toMoney(
                    toMoney($detalle['denominacion'], 2)
                    * (int) $detalle['unidades_por_paquete']
                    * (float) $detalle['cantidad'],
                    4
                );

                return [
                    ...$detalle,
                    'subtotal' => $subtotal,
                ];
            });
            $montoContado = toMoney($detalles->sum('subtotal'), 4);
            $montoSistema = array_key_exists('monto_sistema', $validated) && $validated['monto_sistema'] !== null
                ? toMoney($validated['monto_sistema'], 4)
                : null;

            $arqueo = CajaRuralArqueoCajaChica::query()->create([
                'monto_sistema' => $montoSistema,
                'monto_contado' => $montoContado,
                'diferencia' => $montoSistema === null ? null : toMoney($montoContado - $montoSistema, 4),
                'fecha' => Carbon::parse((string) ($validated['fecha'] ?? now())),
                'usuario_id' => $request->user()->id,
            ]);

            $arqueo->detalles()->createMany($detalles->map(fn (array $detalle): array => [
                'tipo' => $detalle['tipo'],
                'denominacion' => toMoney($detalle['denominacion'], 2),
                'unidades_por_paquete' => $detalle['unidades_por_paquete'],
                'cantidad' => $detalle['cantidad'],
                'subtotal' => $detalle['subtotal'],
            ])->all());

            return $arqueo;
        });

        return response()->json([
            'message' => 'Arqueo de caja chica guardado.',
            'data' => $arqueo->load(['detalles', 'usuario:id,name']),
        ], 201);
    }

    public function storeCuadre(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'capital' => ['required', 'numeric', 'min:0'],
            'banco' => ['required', 'numeric', 'min:0'],
            'caja' => ['required', 'numeric', 'min:0'],
            'fecha' => ['nullable', 'date'],
        ]);

        $cuadre = DB::transaction(function () use ($validated, $request): CajaRuralCuadre {
            $arqueo = CajaRuralArqueoCajaChica::query()->latest('fecha')->latest('id')->first();
            $ultimoCuadre = CajaRuralCuadre::query()->latest('fecha')->latest('id')->first();
            $esAdmin = $request->user()?->role?->code === 'admin';

            $capitalActual = toMoney($ultimoCuadre?->capital ?? 0, 4);
            $capitalSolicitado = toMoney($validated['capital'], 4);

            if (! $esAdmin && abs($capitalSolicitado - $capitalActual) > 0.0001) {
                throw ValidationException::withMessages([
                    'capital' => ['Solo un administrador puede modificar el capital.'],
                ]);
            }

            $capital = $esAdmin ? $capitalSolicitado : $capitalActual;
            $banco = toMoney($validated['banco'], 4);
            $caja = toMoney($validated['caja'], 4);
            $cuentasPorPagarTotal = $this->totalCuenta('cuenta_por_pagar');
            $cuentasPorCobrarTotal = $this->totalCuenta('cuenta_por_cobrar');
            $cajaChica = $arqueo ? toMoney($arqueo->monto_contado, 4) : 0;
            $totalFinanciado = toMoney($capital + $cuentasPorPagarTotal, 4);
            $totalUbicado = toMoney($banco + $caja + $cajaChica + $cuentasPorCobrarTotal, 4);

            return CajaRuralCuadre::query()->create([
                'capital' => $capital,
                'cuentas_por_pagar_total' => $cuentasPorPagarTotal,
                'banco' => $banco,
                'caja' => $caja,
                'caja_chica' => $cajaChica,
                'cuentas_por_cobrar_total' => $cuentasPorCobrarTotal,
                'total_financiado' => $totalFinanciado,
                'total_ubicado' => $totalUbicado,
                'diferencia' => toMoney($totalUbicado - $totalFinanciado, 4),
                'caja_rural_arqueo_caja_chica_id' => $arqueo?->id,
                'fecha' => Carbon::parse((string) ($validated['fecha'] ?? now())),
                'usuario_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Cuadre Caja Rural guardado.',
            'data' => $cuadre->load(['usuario:id,name', 'arqueoCajaChica:id,monto_contado,fecha']),
        ], 201);
    }

    private function totalCuenta(string $tipo): float
    {
        return toMoney(
            CajaRuralDeuda::query()
                ->where('tipo', $tipo)
                ->where('estado', 'activa')
                ->sum('saldo_pendiente'),
            4
        );
    }
}

