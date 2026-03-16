<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalMovimiento extends Model
{
    protected $table = 'capital_movimientos';

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
            'fecha' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(CapitalCuenta::class, 'cuenta_origen_id');
    }

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(CapitalCuenta::class, 'cuenta_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}