<?php

namespace App\Http\Controllers\Capital;

use App\Http\Controllers\Controller;
use App\Models\CapitalCuenta;
use App\Models\CapitalMovimiento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CapitalController extends Controller
{
    public function index(): JsonResponse
    {
        $cuentas = CapitalCuenta::query()
            ->where('activo', true)
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'nombre',
                'tipo',
                'descripcion',
                'saldo_actual',
                'activo',
            ]);

        $movimientos = CapitalMovimiento::query()
            ->with([
                'cuentaOrigen:id,codigo,nombre',
                'cuentaDestino:id,codigo,nombre',
                'usuario:id,name',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'tipo',
                'cuenta_origen_id',
                'cuenta_destino_id',
                'monto',
                'descripcion',
                'referencia_tipo',
                'referencia_id',
                'fecha',
                'usuario_id',
                'meta',
            ]);

        return response()->json([
            'data' => [
                'cuentas' => $cuentas,
                'movimientos' => $movimientos,
                'resumen' => [
                    'saldo_total' => toMoney((float) $cuentas->sum('saldo_actual'), 4),
                ],
            ],
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $cuentas = CapitalCuenta::query()
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'codigo', 'nombre', 'tipo', 'saldo_actual']);

        return response()->json([
            'data' => [
                'cuentas' => $cuentas,
                'tipos_movimiento' => [
                    ['value' => 'ingreso', 'label' => 'Ingreso de capital'],
                    ['value' => 'retiro', 'label' => 'Retiro de capital'],
                    ['value' => 'transferencia', 'label' => 'Transferencia'],
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['ingreso', 'retiro', 'transferencia'])],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha' => ['nullable', 'date'],
            'cuenta_origen_id' => ['nullable', 'integer', Rule::exists('capital_cuentas', 'id')],
            'cuenta_destino_id' => ['nullable', 'integer', Rule::exists('capital_cuentas', 'id')],
        ]);

        $tipo = $validated['tipo'];
        $monto = toMoney($validated['monto'], 4);
        $fecha = Carbon::parse((string) ($validated['fecha'] ?? now()))->toDateTimeString();

        $movimiento = match ($tipo) {
            'ingreso' => registrarMovimientoCapital(
                tipo: 'ingreso_capital',
                monto: $monto,
                usuarioId: (int) $request->user()->id,
                cuentaOrigenId: null,
                cuentaDestinoId: $validated['cuenta_destino_id'] ?? null,
                descripcion: $validated['descripcion'],
                fecha: $fecha
            ),
            'retiro' => registrarMovimientoCapital(
                tipo: 'retiro_capital',
                monto: $monto,
                usuarioId: (int) $request->user()->id,
                cuentaOrigenId: $validated['cuenta_origen_id'] ?? null,
                cuentaDestinoId: null,
                descripcion: $validated['descripcion'],
                fecha: $fecha
            ),
            default => registrarMovimientoCapital(
                tipo: 'transferencia_capital',
                monto: $monto,
                usuarioId: (int) $request->user()->id,
                cuentaOrigenId: $validated['cuenta_origen_id'] ?? null,
                cuentaDestinoId: $validated['cuenta_destino_id'] ?? null,
                descripcion: $validated['descripcion'],
                fecha: $fecha
            ),
        };

        $movimiento->load([
            'cuentaOrigen:id,codigo,nombre',
            'cuentaDestino:id,codigo,nombre',
            'usuario:id,name',
        ]);

        return response()->json([
            'message' => 'Movimiento de capital registrado correctamente.',
            'data' => $movimiento,
        ], 201);
    }
}