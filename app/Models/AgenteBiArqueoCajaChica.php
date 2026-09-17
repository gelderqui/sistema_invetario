<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgenteBiArqueoCajaChica extends Model
{
    protected $table = 'agente_bi_arqueos_caja_chica';

    protected $fillable = [
        'monto_sistema',
        'monto_contado',
        'diferencia',
        'fecha',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto_sistema' => 'decimal:4',
            'monto_contado' => 'decimal:4',
            'diferencia' => 'decimal:4',
            'fecha' => 'datetime',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AgenteBiArqueoDetalle::class, 'agente_bi_arqueo_caja_chica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
