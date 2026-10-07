<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaRuralDeudaMovimiento extends Model
{
    protected $table = 'caja_rural_deuda_movimientos';

    protected $fillable = [
        'caja_rural_deuda_id',
        'tipo',
        'monto',
        'saldo_anterior',
        'saldo_posterior',
        'descripcion',
        'fecha',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
            'saldo_anterior' => 'decimal:4',
            'saldo_posterior' => 'decimal:4',
            'fecha' => 'datetime',
        ];
    }

    public function deuda(): BelongsTo
    {
        return $this->belongsTo(CajaRuralDeuda::class, 'caja_rural_deuda_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

