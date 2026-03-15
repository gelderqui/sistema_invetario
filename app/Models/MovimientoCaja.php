<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    protected $fillable = [
        'caja_id',
        'tipo',
        'monto',
        'descripcion',
        'referencia_tipo',
        'referencia_id',
        'fecha',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:4',
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
