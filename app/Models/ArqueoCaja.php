<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArqueoCaja extends Model
{
    protected $table = 'arqueos_caja';

    protected $fillable = [
        'caja_id',
        'monto_sistema',
        'monto_contado',
        'diferencia',
        'detalle_billetes',
        'usuario_id',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'monto_sistema' => 'decimal:4',
            'monto_contado' => 'decimal:4',
            'diferencia' => 'decimal:4',
            'detalle_billetes' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
