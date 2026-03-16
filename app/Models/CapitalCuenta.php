<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalCuenta extends Model
{
    protected $table = 'capital_cuentas';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'descripcion',
        'saldo_actual',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'saldo_actual' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function movimientosOrigen(): HasMany
    {
        return $this->hasMany(CapitalMovimiento::class, 'cuenta_origen_id');
    }

    public function movimientosDestino(): HasMany
    {
        return $this->hasMany(CapitalMovimiento::class, 'cuenta_destino_id');
    }
}