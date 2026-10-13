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

if (! function_exists('parseMoney')) {
    /**
     * Normaliza importes escritos con punto o coma decimal y separadores de miles.
     * Ejemplos válidos: 18.75, 18,75, 190,234.34 y 190.234,34.
     */
    function parseMoney(float|int|string|null $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim(str_replace([' ', "\u{00A0}"], '', (string) ($value ?? '')));
        if ($raw === '') {
            return 0.0;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        $normalized = $raw;

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';

            $normalized = str_replace($thousandsSeparator, '', $raw);
            if ($decimalSeparator === ',') {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($lastComma !== false) {
            $commaCount = substr_count($raw, ',');
            $decimalLength = strlen($raw) - $lastComma - 1;
            $normalized = $commaCount > 1 || $decimalLength === 3
                ? str_replace(',', '', $raw)
                : str_replace(',', '.', $raw);
        } elseif ($lastDot !== false) {
            $dotCount = substr_count($raw, '.');
            $decimalLength = strlen($raw) - $lastDot - 1;
            if ($dotCount > 1 || $decimalLength === 3) {
                $normalized = str_replace('.', '', $raw);
            }
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}

if (! function_exists('toMoney')) {
    function toMoney(float|int|string|null $value, int $precision = 2): float
    {
        return round(parseMoney($value), $precision);
    }
}

if (! function_exists('denominacionesArqueoPermitidas')) {
    /**
     * @return list<array{tipo: string, denominacion: float, unidades_por_paquete: int}>
     */
    function denominacionesArqueoPermitidas(): array
    {
        return [
            ['tipo' => 'billete', 'denominacion' => 200.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'billete', 'denominacion' => 100.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'billete', 'denominacion' => 50.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'billete', 'denominacion' => 20.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'billete', 'denominacion' => 10.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'billete', 'denominacion' => 5.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'paquete', 'denominacion' => 1.00, 'unidades_por_paquete' => 100],
            ['tipo' => 'moneda', 'denominacion' => 1.00, 'unidades_por_paquete' => 1],
            ['tipo' => 'moneda', 'denominacion' => 0.50, 'unidades_por_paquete' => 1],
            ['tipo' => 'moneda', 'denominacion' => 0.25, 'unidades_por_paquete' => 1],
            ['tipo' => 'moneda', 'denominacion' => 0.10, 'unidades_por_paquete' => 1],
            ['tipo' => 'moneda', 'denominacion' => 0.05, 'unidades_por_paquete' => 1],
        ];
    }
}

if (! function_exists('validarDenominacionesArqueo')) {
    /**
     * @param list<array{tipo: string, denominacion: float|int|string, unidades_por_paquete: int|numeric-string, cantidad: float|int|string}> $detalles
     */
    function validarDenominacionesArqueo(array $detalles): void
    {
        $clave = static fn (string $tipo, float|int|string $denominacion, int|string $unidadesPorPaquete): string => implode('|', [
            $tipo,
            number_format(toMoney($denominacion, 2), 2, '.', ''),
            (string) ((int) $unidadesPorPaquete),
        ]);

        $permitidas = collect(denominacionesArqueoPermitidas())
            ->mapWithKeys(fn (array $denominacion): array => [
                $clave(
                    $denominacion['tipo'],
                    $denominacion['denominacion'],
                    $denominacion['unidades_por_paquete']
                ) => true,
            ]);

        $errores = [];
        $recibidas = [];

        foreach ($detalles as $indice => $detalle) {
            $detalleClave = $clave(
                (string) $detalle['tipo'],
                $detalle['denominacion'],
                $detalle['unidades_por_paquete']
            );

            if (! $permitidas->has($detalleClave)) {
                $errores["detalles.$indice.denominacion"][] = 'La denominación indicada no está permitida para arqueos.';
                continue;
            }

            if (isset($recibidas[$detalleClave])) {
                $errores["detalles.$indice.denominacion"][] = 'No puede repetir una denominación en el mismo arqueo.';
                continue;
            }

            $recibidas[$detalleClave] = true;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
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
