<?php

declare(strict_types=1);

use Carbon\Carbon;
use App\Models\Caja;
use App\Models\CapitalCuenta;
use App\Models\CapitalMovimiento;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

if (! function_exists('getUserPermissions')) {
    function getUserPermissions(User|int|null $user = null): array
    {
        $resolvedUser = match (true) {
            $user instanceof User => $user,
            is_int($user) => User::query()->find($user),
            default => getUser(),
        };

        if (! $resolvedUser instanceof User) {
            return [];
        }

        return $resolvedUser
            ->loadMissing('role.permissions')
            ->allPermissions()
            ->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'code' => $permission->code,
                'module' => $permission->module,
                'ruta' => $permission->ruta,
                'icono' => $permission->icono,
                'orden' => $permission->orden,
            ])
            ->values()
            ->all();
    }
}

if (! function_exists('registrarMovimientoCajaAutomatico')) {
    function registrarMovimientoCajaAutomatico(
        int $usuarioId,
        string $tipo,
        float|int|string $monto,
        ?string $descripcion = null,
        ?string $fecha = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null
    ): ?MovimientoCaja {
        $caja = Caja::query()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'abierta')
            ->latest('id')
            ->first();

        if (! $caja) {
            return null;
        }

        return MovimientoCaja::query()->create([
            'caja_id' => $caja->id,
            'tipo' => $tipo,
            'monto' => toMoney($monto, 4),
            'descripcion' => $descripcion,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'fecha' => $fecha ?? now()->toDateTimeString(),
            'usuario_id' => $usuarioId,
        ]);
    }
}

if (! function_exists('capitalCuentaPorCodigo')) {
    function capitalCuentaPorCodigo(string $codigo): ?CapitalCuenta
    {
        return CapitalCuenta::query()
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->first();
    }
}

if (! function_exists('registrarMovimientoCapital')) {
    function registrarMovimientoCapital(
        string $tipo,
        float|int|string $monto,
        ?int $usuarioId = null,
        ?int $cuentaOrigenId = null,
        ?int $cuentaDestinoId = null,
        ?string $descripcion = null,
        ?string $fecha = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        array $meta = []
    ): CapitalMovimiento {
        $montoNormalizado = toMoney($monto, 4);

        if ($montoNormalizado <= 0) {
            throw ValidationException::withMessages([
                'monto' => ['El monto del movimiento de capital debe ser mayor que cero.'],
            ]);
        }

        if ($cuentaOrigenId === null && $cuentaDestinoId === null) {
            throw ValidationException::withMessages([
                'cuenta_origen_id' => ['Debe indicar al menos una cuenta origen o destino.'],
            ]);
        }

        if ($cuentaOrigenId !== null && $cuentaDestinoId !== null && $cuentaOrigenId === $cuentaDestinoId) {
            throw ValidationException::withMessages([
                'cuenta_destino_id' => ['La cuenta origen y destino deben ser diferentes.'],
            ]);
        }

        return DB::transaction(function () use (
            $tipo,
            $montoNormalizado,
            $usuarioId,
            $cuentaOrigenId,
            $cuentaDestinoId,
            $descripcion,
            $fecha,
            $referenciaTipo,
            $referenciaId,
            $meta
        ): CapitalMovimiento {
            $cuentaOrigen = $cuentaOrigenId
                ? CapitalCuenta::query()->lockForUpdate()->find($cuentaOrigenId)
                : null;
            $cuentaDestino = $cuentaDestinoId
                ? CapitalCuenta::query()->lockForUpdate()->find($cuentaDestinoId)
                : null;

            if ($cuentaOrigenId !== null && ! $cuentaOrigen) {
                throw ValidationException::withMessages([
                    'cuenta_origen_id' => ['La cuenta origen seleccionada no existe.'],
                ]);
            }

            if ($cuentaDestinoId !== null && ! $cuentaDestino) {
                throw ValidationException::withMessages([
                    'cuenta_destino_id' => ['La cuenta destino seleccionada no existe.'],
                ]);
            }

            if ($cuentaOrigen && ! $cuentaOrigen->activo) {
                throw ValidationException::withMessages([
                    'cuenta_origen_id' => ['La cuenta origen no esta activa.'],
                ]);
            }

            if ($cuentaDestino && ! $cuentaDestino->activo) {
                throw ValidationException::withMessages([
                    'cuenta_destino_id' => ['La cuenta destino no esta activa.'],
                ]);
            }

            if ($cuentaOrigen && (float) $cuentaOrigen->saldo_actual < $montoNormalizado) {
                throw ValidationException::withMessages([
                    'monto' => ['Saldo insuficiente en '.$cuentaOrigen->nombre.'.'],
                ]);
            }

            if ($cuentaOrigen) {
                $cuentaOrigen->saldo_actual = toMoney((float) $cuentaOrigen->saldo_actual - $montoNormalizado, 4);
                $cuentaOrigen->save();
            }

            if ($cuentaDestino) {
                $cuentaDestino->saldo_actual = toMoney((float) $cuentaDestino->saldo_actual + $montoNormalizado, 4);
                $cuentaDestino->save();
            }

            return CapitalMovimiento::query()->create([
                'tipo' => $tipo,
                'cuenta_origen_id' => $cuentaOrigen?->id,
                'cuenta_destino_id' => $cuentaDestino?->id,
                'monto' => $montoNormalizado,
                'descripcion' => $descripcion,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'fecha' => $fecha ?? now()->toDateTimeString(),
                'usuario_id' => $usuarioId,
                'meta' => $meta === [] ? null : $meta,
            ]);
        });
    }
}
