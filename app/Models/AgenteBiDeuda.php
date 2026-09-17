<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgenteBiDeuda extends Model
{
    protected $table = 'agente_bi_deudas';

    protected $fillable = [
        'tipo',
        'nombre_referencia',
        'descripcion',
        'monto_original',
        'saldo_pendiente',
        'fecha_origen',
        'estado',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto_original' => 'decimal:4',
            'saldo_pendiente' => 'decimal:4',
            'fecha_origen' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(AgenteBiDeudaMovimiento::class, 'agente_bi_deuda_id');
    }
}
