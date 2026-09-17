<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgenteBiCuadre extends Model
{
    protected $table = 'agente_bi_cuadres';

    protected $fillable = [
        'capital',
        'bi_debe_total',
        'banco',
        'caja',
        'caja_chica',
        'deuda_a_bi_total',
        'total_financiado',
        'total_ubicado',
        'diferencia',
        'agente_bi_arqueo_caja_chica_id',
        'fecha',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:4',
            'bi_debe_total' => 'decimal:4',
            'banco' => 'decimal:4',
            'caja' => 'decimal:4',
            'caja_chica' => 'decimal:4',
            'deuda_a_bi_total' => 'decimal:4',
            'total_financiado' => 'decimal:4',
            'total_ubicado' => 'decimal:4',
            'diferencia' => 'decimal:4',
            'fecha' => 'datetime',
        ];
    }

    public function arqueoCajaChica(): BelongsTo
    {
        return $this->belongsTo(AgenteBiArqueoCajaChica::class, 'agente_bi_arqueo_caja_chica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
