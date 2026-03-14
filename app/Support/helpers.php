<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

if (! function_exists('getUser')) {
    function getUser(): ?Authenticatable
    {
        return Auth::user();
    }
}

if (! function_exists('getUserId')) {
    function getUserId(): ?int
    {
        $id = getUser()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}

if (! function_exists('getFechaHora')) {
    function getFechaHora(?string $tz = null): string
    {
        return Carbon::now($tz)->toDateTimeString();
    }
}

if (! function_exists('toMoney')) {
    function toMoney(float|int|string|null $value, int $precision = 2): float
    {
        return round((float) ($value ?? 0), $precision);
    }
}

if (! function_exists('weightedAverageCost')) {
    function weightedAverageCost(
        float|int|string|null $currentStock,
        float|int|string|null $currentCost,
        float|int|string|null $incomingQty,
        float|int|string|null $incomingCost
    ): float {
        $stockActual = (float) ($currentStock ?? 0);
        $costoActual = (float) ($currentCost ?? 0);
        $cantidadEntrada = (float) ($incomingQty ?? 0);
        $costoEntrada = (float) ($incomingCost ?? 0);

        if ($cantidadEntrada <= 0) {
            return toMoney($costoActual, 4);
        }

        $stockBase = max($stockActual, 0);
        $totalQty = $stockBase + $cantidadEntrada;

        if ($totalQty <= 0) {
            return 0.0;
        }

        $weighted = (($stockBase * $costoActual) + ($cantidadEntrada * $costoEntrada)) / $totalQty;

        return toMoney($weighted, 4);
    }
}
