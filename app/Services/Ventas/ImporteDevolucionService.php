<?php

namespace App\Services\Ventas;

use App\Models\Venta;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class ImporteDevolucionService
{
    public function centavos(float|int|string $monto): int
    {
        return BigDecimal::of((string) $monto)
            ->toScale(2, RoundingMode::HalfUp)
            ->getUnscaledValue()
            ->toInt();
    }

    /**
     * Reparte el total neto entre los detalles originales, asignando los
     * centavos sobrantes al mayor residuo y usando el ID para desempatar.
     *
     * @return array<int, int> Importe neto de cada detalle, en centavos.
     */
    public function distribuirNetoPorDetalle(Venta $venta): array
    {
        $detalles = $venta->detalles->sortBy('id');
        $brutos = $detalles->mapWithKeys(fn ($detalle) => [
            $detalle->id => max(0, $this->centavos($detalle->subtotal)),
        ]);
        $brutoTotal = $brutos->sum();
        $netoTotal = $this->centavos($venta->total);

        if ($netoTotal < 0 || $netoTotal > $brutoTotal) {
            throw ValidationException::withMessages([
                'venta_id' => ['Los importes originales de la venta no permiten calcular su devolución. Revise la venta.'],
            ]);
        }

        if ($netoTotal === 0) {
            return $brutos->map(fn () => 0)->all();
        }

        $importes = [];
        $residuos = [];

        foreach ($brutos as $id => $bruto) {
            [$importe, $residuo] = BigInteger::of($netoTotal)
                ->multipliedBy($bruto)
                ->quotientAndRemainder($brutoTotal);

            $importes[$id] = $importe->toInt();
            $residuos[] = ['id' => $id, 'residuo' => $residuo->toInt()];
        }

        usort($residuos, fn ($a, $b) => ($b['residuo'] <=> $a['residuo']) ?: ($a['id'] <=> $b['id']));
        $sobrantes = $netoTotal - array_sum($importes);

        for ($i = 0; $i < $sobrantes; $i++) {
            $importes[$residuos[$i]['id']]++;
        }

        return $importes;
    }

    public function calcularParcial(
        int $netoDetalle,
        int $cantidadVendida,
        int $cantidadDevuelta,
        int $importeDevuelto,
        int $cantidad
    ): int {
        $cantidadDisponible = $cantidadVendida - $cantidadDevuelta;
        $importeDisponible = $netoDetalle - $importeDevuelto;

        if ($importeDisponible < 0) {
            throw ValidationException::withMessages([
                'venta_id' => ['Una devolución anterior supera el importe neto de este producto. Revise el historial antes de continuar.'],
            ]);
        }

        if ($cantidad < 1 || $cantidad > $cantidadDisponible) {
            throw ValidationException::withMessages([
                'items' => ['La cantidad a devolver supera las unidades disponibles de la venta.'],
            ]);
        }

        // Repartir el saldo restante permite devolver o anular en cualquier
        // orden sin perder centavos ni volver a reembolsarlos dos veces.
        return BigInteger::of($importeDisponible)
            ->multipliedBy($cantidad)
            ->quotient($cantidadDisponible)
            ->toInt();
    }
}
