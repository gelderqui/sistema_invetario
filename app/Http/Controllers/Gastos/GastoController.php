<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CapitalCuenta;
use App\Models\Gasto;
use App\Models\TipoGasto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function catalogs(Request $request): JsonResponse
    {
        $tipos = TipoGasto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);

        $cajaActiva = Caja::query()
            ->where('usuario_id', $request->user()->id)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first(['id', 'fecha_apertura']);

        $metodosPago = [
            ['value' => 'caja_general', 'label' => 'Caja general'],
            ['value' => 'banco', 'label' => 'Banco'],
        ];

        if ($cajaActiva) {
            array_unshift($metodosPago, ['value' => 'caja', 'label' => 'Caja']);
        }

        $capitalCuentas = CapitalCuenta::query()
            ->whereIn('codigo', ['caja_general', 'banco'])
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'codigo', 'nombre', 'saldo_actual']);

        return response()->json([
            'data' => [
                'tipos_gasto' => $tipos,
                'metodos_pago' => $metodosPago,
                'caja_activa' => $cajaActiva,
                'capital_cuentas' => $capitalCuentas,
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
            'metodo_pago' => ['required', Rule::in(['caja', 'caja_general', 'banco'])],
        ]);

        $cajaActiva = null;
        if ($validated['metodo_pago'] === 'caja') {
            $cajaActiva = Caja::query()
                ->where('usuario_id', $request->user()->id)
                ->where('estado', 'abierta')
                ->latest('id')
                ->first();

            if (! $cajaActiva) {
                throw ValidationException::withMessages([
                    'metodo_pago' => ['Solo puede usar Caja si el usuario tiene una caja abierta.'],
                ]);
            }

            $fechaGasto = Carbon::parse((string) $validated['fecha'])->toDateString();
            $fechaCaja = Carbon::parse((string) $cajaActiva->fecha_apertura)->toDateString();
            if ($fechaGasto !== $fechaCaja) {
                throw ValidationException::withMessages([
                    'fecha' => ['La fecha del gasto debe coincidir con el dia de la caja abierta cuando el metodo de pago es Caja.'],
                ]);
            }
        }

        $gasto = DB::transaction(function () use ($validated, $request) {
            $gasto = Gasto::query()->create([
                ...$validated,
                'monto' => toMoney($validated['monto'], 4),
                'usuario_id' => $request->user()->id,
            ]);

            if ($gasto->metodo_pago === 'caja') {
                registrarMovimientoCajaAutomatico(
                    (int) $request->user()->id,
                    'gasto',
                    -1 * (float) $gasto->monto,
                    'Salida por gasto: '.$gasto->descripcion,
                    $gasto->fecha?->toDateString(),
                    'gasto',
                    $gasto->id
                );

                return $gasto;
            }

            $cuentaCapital = capitalCuentaPorCodigo($gasto->metodo_pago);

            if (! $cuentaCapital) {
                throw ValidationException::withMessages([
                    'metodo_pago' => ['No existe una cuenta activa para el metodo de pago seleccionado.'],
                ]);
            }

            registrarMovimientoCapital(
                tipo: 'gasto',
                monto: (float) $gasto->monto,
                usuarioId: (int) $request->user()->id,
                cuentaOrigenId: $cuentaCapital->id,
                cuentaDestinoId: null,
                descripcion: 'Salida por gasto: '.$gasto->descripcion,
                fecha: Carbon::parse((string) $gasto->fecha)->toDateTimeString(),
                referenciaTipo: 'gasto',
                referenciaId: $gasto->id,
                meta: [
                    'metodo_pago' => $gasto->metodo_pago,
                ]
            );

            return $gasto;
        });

        $gasto->load(['tipoGasto:id,nombre', 'usuario:id,name']);

        return response()->json([
            'message' => 'Gasto registrado correctamente.',
            'data' => $gasto,
        ], 201);
    }
}
