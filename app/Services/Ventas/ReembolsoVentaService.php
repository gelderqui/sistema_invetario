<?php

namespace App\Services\Ventas;

use App\Models\Caja;
use App\Models\CapitalMovimiento;
use App\Models\Devolucion;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Models\VentaCredito;
use App\Models\VentaCreditoMovimiento;
use Illuminate\Validation\ValidationException;

class ReembolsoVentaService
{
    public function anularVenta(Venta $venta, int $usuarioId): void
    {
        $monto = toMoney($venta->total, 4);
        if ($monto <= 0) {
            return;
        }

        $cajaOriginal = $this->cajaDeVenta($venta);
        if ($cajaOriginal->estado === 'cerrada') {
            $this->movimientoCapital('anulacion_venta', $monto, $usuarioId, 'venta', (int) $venta->id,
                'Reembolso por anulación de venta '.$venta->numero.' con caja POS #'.$cajaOriginal->id.' cerrada',
                ['caja_venta_id' => $cajaOriginal->id]);
            return;
        }

        if ($cajaOriginal->estado !== 'abierta' || (int) $cajaOriginal->usuario_id !== $usuarioId) {
            throw ValidationException::withMessages([
                'caja' => ['No se puede reembolsar desde la caja original de esta venta.'],
            ]);
        }

        $this->movimientoCaja($cajaOriginal, 'anulacion_venta', -$monto, $usuarioId, 'venta', (int) $venta->id,
            'Anulación de venta '.$venta->numero);
    }

    /** @return array{caja: ?MovimientoCaja, capital: ?CapitalMovimiento} */
    public function devolver(Venta $venta, Devolucion $devolucion, float $monto, int $usuarioId): array
    {
        if ($monto <= 0) {
            return ['caja' => null, 'capital' => null];
        }

        $cajaCerrada = $venta->metodo_pago === 'efectivo'
            ? $this->cajaDeVenta($venta)->estado === 'cerrada'
            : $this->cobroCreditoConCajaCerrada($venta);

        if ($cajaCerrada) {
            return [
                'caja' => null,
                'capital' => $this->movimientoCapital('devolucion_venta', $monto, $usuarioId, 'devolucion', (int) $devolucion->id,
                    'Reembolso por devolución DEV-'.$devolucion->id.' de venta '.$venta->numero.' con caja cerrada',
                    ['venta_id' => $venta->id]),
            ];
        }

        $cajaActual = Caja::query()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'abierta')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $cajaActual) {
            throw ValidationException::withMessages([
                'caja' => ['Debe tener una caja abierta para registrar el reembolso.'],
            ]);
        }

        return [
            'caja' => $this->movimientoCaja($cajaActual, 'devolucion_venta', -$monto, $usuarioId,
                'devolucion', (int) $devolucion->id,
                'Reembolso por devolución DEV-'.$devolucion->id.' de venta '.$venta->numero),
            'capital' => null,
        ];
    }

    public function revertirDevolucion(Devolucion $devolucion, int $usuarioId): void
    {
        $monto = toMoney($devolucion->monto_reembolso_efectivo ?? 0, 4);
        if ($monto <= 0) {
            return;
        }

        if ($devolucion->movimiento_capital_id) {
            if ($devolucion->movimiento_caja_id) {
                throw ValidationException::withMessages(['devolucion' => ['El reembolso tiene dos orígenes de dinero. Revise su historial.']]);
            }

            $original = CapitalMovimiento::query()->lockForUpdate()->findOrFail($devolucion->movimiento_capital_id);
            if ($original->tipo !== 'devolucion_venta' || $original->referencia_tipo !== 'devolucion'
                || (int) $original->referencia_id !== (int) $devolucion->id
                || abs((float) $original->monto - $monto) > 0.0001) {
                throw ValidationException::withMessages(['devolucion' => ['No coincide el movimiento original del reembolso en Caja general.']]);
            }

            $reversion = $this->movimientoCapital('anulacion_devolucion_venta', $monto, $usuarioId,
                'devolucion', (int) $devolucion->id,
                'Reintegro por anulación de devolución DEV-'.$devolucion->id,
                ['movimiento_origen_id' => $original->id], true);
            $devolucion->movimiento_capital_reversion_id = $reversion->id;
        } elseif ($devolucion->movimiento_caja_id) {
            $original = MovimientoCaja::query()->lockForUpdate()->findOrFail($devolucion->movimiento_caja_id);
            $caja = Caja::query()->lockForUpdate()->findOrFail($original->caja_id);
            if ($original->tipo !== 'devolucion_venta' || $original->referencia_tipo !== 'devolucion'
                || (int) $original->referencia_id !== (int) $devolucion->id
                || abs((float) $original->monto + $monto) > 0.0001) {
                throw ValidationException::withMessages(['devolucion' => ['No coincide el movimiento original del reembolso en caja.']]);
            }

            if ($caja->estado === 'cerrada') {
                $reversion = $this->movimientoCapital('anulacion_devolucion_venta', $monto, $usuarioId,
                    'devolucion', (int) $devolucion->id,
                    'Reintegro por anulación de devolución DEV-'.$devolucion->id.' con caja POS #'.$caja->id.' cerrada',
                    ['movimiento_caja_origen_id' => $original->id, 'caja_id' => $caja->id], true);
                $devolucion->movimiento_capital_reversion_id = $reversion->id;
            } else {
                if ($caja->estado !== 'abierta' || (int) $caja->usuario_id !== $usuarioId) {
                    throw ValidationException::withMessages([
                        'caja' => ['Solo puede anular el reembolso desde la misma caja abierta por el usuario que lo realizó.'],
                    ]);
                }

                $reversion = $this->movimientoCaja($caja, 'anulacion_devolucion_venta', $monto,
                    $usuarioId, 'devolucion', (int) $devolucion->id,
                    'Anulación de reembolso por devolución DEV-'.$devolucion->id);
                $devolucion->movimiento_caja_reversion_id = $reversion->id;
            }
        } else {
            throw ValidationException::withMessages([
                'devolucion' => ['La devolución no tiene trazabilidad del reembolso en efectivo.'],
            ]);
        }

        $devolucion->save();
    }

    private function cajaDeVenta(Venta $venta): Caja
    {
        $ingreso = MovimientoCaja::query()
            ->where('tipo', 'venta')
            ->where('referencia_tipo', 'venta')
            ->where('referencia_id', $venta->id)
            ->lockForUpdate()
            ->first();

        if (! $ingreso) {
            throw ValidationException::withMessages([
                'venta' => ['No se encontró el ingreso original de esta venta en caja. Revise el historial antes de reembolsar.'],
            ]);
        }

        if (abs((float) $ingreso->monto - (float) $venta->total) > 0.0001) {
            throw ValidationException::withMessages([
                'venta' => ['El ingreso original no coincide con el total de la venta. Revise el historial antes de reembolsar.'],
            ]);
        }

        return Caja::query()->lockForUpdate()->findOrFail($ingreso->caja_id);
    }

    private function cobroCreditoConCajaCerrada(Venta $venta): bool
    {
        $credito = VentaCredito::query()->where('venta_id', $venta->id)->first();
        if (! $credito) {
            throw ValidationException::withMessages(['venta' => ['No se encontró la cuenta por cobrar de esta venta.']]);
        }

        $abonos = VentaCreditoMovimiento::query()
            ->where('venta_credito_id', $credito->id)
            ->where('tipo', 'abono')
            ->whereDoesntHave('reversion')
            ->get(['id', 'movimiento_caja_id']);

        if ($abonos->isEmpty()) {
            throw ValidationException::withMessages(['venta' => ['No se encontró un abono vigente para respaldar el reembolso.']]);
        }

        if ($abonos->contains(fn (VentaCreditoMovimiento $abono) => ! $abono->movimiento_caja_id)) {
            throw ValidationException::withMessages(['venta' => ['Un abono no tiene trazabilidad de caja. Revise el historial antes de reembolsar.']]);
        }

        $cajas = MovimientoCaja::query()->whereIn('id', $abonos->pluck('movimiento_caja_id'))
            ->get(['caja_id'])->pluck('caja_id')->unique();
        if ($cajas->isEmpty()) {
            throw ValidationException::withMessages(['venta' => ['No se encontró la caja de los abonos de esta venta.']]);
        }

        $estados = Caja::query()->whereIn('id', $cajas)->pluck('estado');
        if ($estados->count() !== $cajas->count()) {
            throw ValidationException::withMessages(['venta' => ['No se encontró una caja de los abonos de esta venta.']]);
        }

        return $estados->contains('cerrada');
    }

    private function movimientoCaja(Caja $caja, string $tipo, float $monto, int $usuarioId,
        string $referenciaTipo, int $referenciaId, string $descripcion): MovimientoCaja
    {
        return MovimientoCaja::query()->create([
            'caja_id' => $caja->id,
            'tipo' => $tipo,
            'monto' => toMoney($monto, 4),
            'descripcion' => $descripcion,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'fecha' => now(),
            'usuario_id' => $usuarioId,
        ]);
    }

    private function movimientoCapital(string $tipo, float $monto, int $usuarioId, string $referenciaTipo,
        int $referenciaId, string $descripcion, array $meta = [], bool $ingreso = false): CapitalMovimiento
    {
        $cajaGeneral = capitalCuentaPorCodigo('caja_general');
        if (! $cajaGeneral) {
            throw ValidationException::withMessages([
                'caja' => ['No existe una Caja general activa para registrar el reembolso.'],
            ]);
        }

        return registrarMovimientoCapital(
            tipo: $tipo,
            monto: $monto,
            usuarioId: $usuarioId,
            cuentaOrigenId: $ingreso ? null : (int) $cajaGeneral->id,
            cuentaDestinoId: $ingreso ? (int) $cajaGeneral->id : null,
            descripcion: $descripcion,
            fecha: now()->toDateTimeString(),
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            meta: $meta,
        );
    }
}
