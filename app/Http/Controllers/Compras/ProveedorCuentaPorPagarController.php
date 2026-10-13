<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CapitalCuenta;
use App\Models\Proveedor;
use App\Models\ProveedorCuentaPorPagar;
use App\Models\ProveedorPago;
use App\Models\ProveedorPagoAplicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProveedorCuentaPorPagarController extends Controller
{
    public function index(): JsonResponse
    {
        $cuentas = ProveedorCuentaPorPagar::query()
            ->with(['proveedor:id,nombre', 'venta:id,numero,fecha_venta', 'compra:id,numero'])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('proveedor_id')->orderBy('id')->get();

        return response()->json(['data' => $cuentas]);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json(['data' => [
            'proveedores' => Proveedor::query()->where('activo', true)->orderBy('nombre')->get(['id','nombre']),
            'metodos_pago' => [['value' => 'caja_general', 'label' => 'Caja general'], ['value' => 'banco', 'label' => 'Banco']],
            'capital_cuentas' => CapitalCuenta::query()->whereIn('codigo', ['caja_general','banco'])->where('activo', true)->get(['id','codigo','nombre','saldo_actual']),
        ]]);
    }

    public function pagos(): JsonResponse
    {
        return response()->json(['data' => ProveedorPago::query()->with('proveedor:id,nombre')->latest('fecha')->latest('id')->limit(200)->get()]);
    }

    public function pagar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')],
            'metodo_pago' => ['required', Rule::in(['caja_general', 'banco'])],
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.cuenta_id' => ['required', 'integer', Rule::exists('proveedor_cuentas_por_pagar', 'id'), 'distinct'],
            'aplicaciones.*.monto' => ['required', 'numeric', 'gt:0'],
        ]);

        $pago = DB::transaction(function () use ($validated, $request) {
            $cuentaCapital = capitalCuentaPorCodigo($validated['metodo_pago']);
            if (! $cuentaCapital) throw ValidationException::withMessages(['metodo_pago' => ['La cuenta seleccionada no está disponible.']]);
            $total = 0.0;
            $cuentas = [];
            foreach ($validated['aplicaciones'] as $aplicacion) {
                $cuenta = ProveedorCuentaPorPagar::query()->lockForUpdate()->findOrFail($aplicacion['cuenta_id']);
                if ($cuenta->proveedor_id !== (int) $validated['proveedor_id'] || ! in_array($cuenta->estado, ['pendiente','parcial'], true)) {
                    throw ValidationException::withMessages(['aplicaciones' => ['Hay cargos que no están disponibles para este proveedor.']]);
                }
                $monto = toMoney($aplicacion['monto'], 4);
                if ($monto > (float) $cuenta->saldo_pendiente + 0.0001) {
                    throw ValidationException::withMessages(['aplicaciones' => ["El pago supera el saldo del cargo #{$cuenta->id}."]]);
                }
                $cuentas[] = [$cuenta, $monto]; $total += $monto;
            }
            $total = toMoney($total, 4);
            $pago = ProveedorPago::query()->create([
                'proveedor_id' => $validated['proveedor_id'], 'metodo_pago' => $validated['metodo_pago'], 'monto' => $total,
                'fecha' => $validated['fecha'], 'numero' => sprintf('PPR-%s-%03d', now()->format('YmdHis'), random_int(1,999)),
                'observaciones' => $validated['observaciones'] ?? null, 'add_user' => $request->user()->id,
            ]);
            foreach ($cuentas as [$cuenta, $monto]) {
                $saldo = toMoney((float) $cuenta->saldo_pendiente - $monto, 4);
                $cuenta->update(['saldo_pendiente' => $saldo, 'estado' => $saldo <= 0.0001 ? 'pagada' : 'parcial']);
                ProveedorPagoAplicacion::query()->create(['proveedor_pago_id' => $pago->id, 'proveedor_cuenta_por_pagar_id' => $cuenta->id, 'monto' => $monto]);
            }
            registrarMovimientoCapital('pago_proveedor_consignacion', $total, (int) $request->user()->id, (int) $cuentaCapital->id, null,
                'Pago de consignación '.$pago->numero, $pago->fecha->toDateTimeString(), 'proveedor_pago', $pago->id, ['proveedor_id' => $pago->proveedor_id]);
            return $pago;
        });
        return response()->json(['message' => 'Pago al proveedor registrado correctamente.', 'data' => $pago->load('proveedor:id,nombre')], 201);
    }
}
