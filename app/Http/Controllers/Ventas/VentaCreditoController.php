<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\VentaCredito;
use App\Models\VentaCreditoMovimiento;
use App\Models\MovimientoCaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaCreditoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', 'in:activa,pagada,vencida,anulada,incobrable,todas'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $estado = $validated['estado'] ?? 'activa';
        $perPage = $validated['per_page'] ?? 10;

        $creditos = VentaCredito::query()
            ->with(['cliente:id,nombre,telefono', 'venta:id,numero,fecha_venta,total'])
            ->when($estado === 'vencida', fn ($query) => $query
                ->where('estado', 'activa')
                ->whereDate('fecha_vencimiento', '<', now()->toDateString()))
            ->when(! in_array($estado, ['todas', 'vencida'], true), fn ($query) => $query->where('estado', $estado))
            ->orderByRaw("case when estado = 'activa' and fecha_vencimiento < ? then 0 else 1 end", [now()->toDateString()])
            ->orderBy('fecha_vencimiento')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $creditos->items(),
            'meta' => [
                'current_page' => $creditos->currentPage(),
                'total' => $creditos->total(),
            ],
        ]);
    }

    public function movimientos(VentaCredito $credito): JsonResponse
    {
        return response()->json([
            'data' => $credito->movimientos()
                ->with(['usuario:id,username', 'reversion:id,movimiento_origen_id'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function abonar(Request $request, VentaCredito $credito): JsonResponse
    {
        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $request->user()->id;

        $credito = DB::transaction(function () use ($credito, $validated, $userId): VentaCredito {
            $credito = VentaCredito::query()->lockForUpdate()->findOrFail($credito->id);

            if ($credito->estado !== 'activa') {
                throw ValidationException::withMessages(['credito' => ['Solo se pueden recibir abonos en cuentas activas.']]);
            }

            $saldoAnterior = toMoney($credito->saldo_pendiente, 4);
            $monto = toMoney($validated['monto'], 4);

            if ($monto > $saldoAnterior) {
                throw ValidationException::withMessages(['monto' => ['El abono no puede superar el saldo pendiente.']]);
            }

            $movimientoCaja = registrarMovimientoCajaAutomatico(
                $userId,
                'abono_venta_credito',
                $monto,
                'Abono de '.$credito->cliente->nombre.' a venta '.$credito->venta->numero,
                now()->toDateTimeString(),
                'venta_credito',
                (int) $credito->id,
            );

            if (! $movimientoCaja) {
                throw ValidationException::withMessages(['caja' => ['Debe tener una caja abierta para registrar un abono en efectivo.']]);
            }

            $saldoPosterior = toMoney($saldoAnterior - $monto, 4);
            $credito->update([
                'saldo_pendiente' => $saldoPosterior,
                'estado' => $saldoPosterior === 0.0 ? 'pagada' : 'activa',
                'mod_user' => $userId,
            ]);

            VentaCreditoMovimiento::query()->create([
                'venta_credito_id' => $credito->id,
                'tipo' => 'abono',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'fecha' => now(),
                'metodo_pago' => 'efectivo',
                'descripcion' => $validated['descripcion'] ?? 'Abono recibido en efectivo.',
                'usuario_id' => $userId,
                'movimiento_caja_id' => $movimientoCaja->id,
            ]);

            return $credito;
        });

        return response()->json([
            'message' => 'Abono registrado correctamente.',
            'data' => $credito->fresh()->load(['cliente:id,nombre,telefono', 'venta:id,numero,fecha_venta,total']),
        ]);
    }

    public function anularAbono(Request $request, VentaCredito $credito, VentaCreditoMovimiento $movimiento): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($credito, $movimiento, $validated, $userId): void {
            $credito = VentaCredito::query()->lockForUpdate()->findOrFail($credito->id);
            $movimiento = VentaCreditoMovimiento::query()->lockForUpdate()->findOrFail($movimiento->id);

            if ($movimiento->venta_credito_id !== $credito->id || $movimiento->tipo !== 'abono') {
                throw ValidationException::withMessages(['abono' => ['El movimiento seleccionado no es un abono de esta cuenta.']]);
            }

            if ($movimiento->movimiento_origen_id || $movimiento->reversion()->exists()) {
                throw ValidationException::withMessages(['abono' => ['Este abono ya fue anulado.']]);
            }

            $hayAbonoPosterior = VentaCreditoMovimiento::query()
                ->where('venta_credito_id', $credito->id)
                ->where('tipo', 'abono')
                ->where('id', '>', $movimiento->id)
                ->whereDoesntHave('reversion')
                ->exists();

            if ($hayAbonoPosterior) {
                throw ValidationException::withMessages(['abono' => ['Solo puede anular el último abono vigente de la cuenta.']]);
            }

            if (! in_array($credito->estado, ['activa', 'pagada'], true)) {
                throw ValidationException::withMessages(['credito' => ['No se puede anular un abono de una cuenta anulada o incobrable.']]);
            }

            if (! $movimiento->movimiento_caja_id) {
                throw ValidationException::withMessages(['abono' => ['Este abono antiguo no tiene trazabilidad de caja y no puede anularse automáticamente.']]);
            }

            $movimientoCajaOriginal = MovimientoCaja::query()
                ->with('caja:id,usuario_id,estado')
                ->lockForUpdate()
                ->findOrFail($movimiento->movimiento_caja_id);

            if (! $movimientoCajaOriginal->caja || $movimientoCajaOriginal->caja->estado !== 'abierta' || (int) $movimientoCajaOriginal->caja->usuario_id !== $userId) {
                throw ValidationException::withMessages(['caja' => ['Solo puede anular el abono desde la misma caja abierta por el usuario que lo recibió.']]);
            }

            $monto = toMoney($movimiento->monto, 4);
            $saldoAnterior = toMoney($credito->saldo_pendiente, 4);
            $saldoPosterior = toMoney($saldoAnterior + $monto, 4);

            if ($saldoPosterior > toMoney($credito->monto_original, 4)) {
                throw ValidationException::withMessages(['abono' => ['La anulación excedería el saldo original de la cuenta.']]);
            }

            $movimientoCajaReversion = MovimientoCaja::query()->create([
                'caja_id' => $movimientoCajaOriginal->caja_id,
                'tipo' => 'anulacion_abono_venta_credito',
                'monto' => -$monto,
                'descripcion' => 'Anulación de abono de venta '.$credito->venta->numero.'. Motivo: '.$validated['motivo'],
                'referencia_tipo' => 'venta_credito',
                'referencia_id' => $credito->id,
                'fecha' => now(),
                'usuario_id' => $userId,
            ]);

            $credito->update([
                'saldo_pendiente' => $saldoPosterior,
                'estado' => 'activa',
                'mod_user' => $userId,
            ]);

            VentaCreditoMovimiento::query()->create([
                'venta_credito_id' => $credito->id,
                'tipo' => 'anulacion_abono',
                'monto' => $monto,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'fecha' => now(),
                'metodo_pago' => 'efectivo',
                'descripcion' => 'Anulación de abono. Motivo: '.$validated['motivo'],
                'usuario_id' => $userId,
                'movimiento_caja_id' => $movimientoCajaReversion->id,
                'movimiento_origen_id' => $movimiento->id,
            ]);
        });

        return response()->json(['message' => 'Abono anulado y saldo de la cuenta restituido correctamente.']);
    }

    public function marcarIncobrable(Request $request, VentaCredito $credito): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($credito, $validated, $userId): void {
            $credito = VentaCredito::query()->lockForUpdate()->findOrFail($credito->id);

            if ($credito->estado !== 'activa' || toMoney($credito->saldo_pendiente, 4) <= 0) {
                throw ValidationException::withMessages(['credito' => ['Solo se puede marcar como incobrable una cuenta activa con saldo pendiente.']]);
            }

            $saldoAnterior = toMoney($credito->saldo_pendiente, 4);
            $credito->update([
                'saldo_pendiente' => 0,
                'estado' => 'incobrable',
                'mod_user' => $userId,
            ]);

            VentaCreditoMovimiento::query()->create([
                'venta_credito_id' => $credito->id,
                'tipo' => 'incobrable',
                'monto' => $saldoAnterior,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => 0,
                'fecha' => now(),
                'descripcion' => 'Cuenta marcada como incobrable. Motivo: '.$validated['motivo'],
                'usuario_id' => $userId,
            ]);
        });

        return response()->json(['message' => 'Cuenta marcada como incobrable y pérdida registrada correctamente.']);
    }
}
