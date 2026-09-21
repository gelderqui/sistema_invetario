<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\VentaCredito;
use App\Models\VentaCreditoMovimiento;
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
                ->with('usuario:id,name')
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
            ]);

            return $credito;
        });

        return response()->json([
            'message' => 'Abono registrado correctamente.',
            'data' => $credito->fresh()->load(['cliente:id,nombre,telefono', 'venta:id,numero,fecha_venta,total']),
        ]);
    }
}
