<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgenteBiDeudaMovimiento extends Model
{
    protected $table = 'agente_bi_deuda_movimientos';

    protected $fillable = [
        'agente_bi_deuda_id',
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
        return $this->belongsTo(AgenteBiDeuda::class, 'agente_bi_deuda_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
