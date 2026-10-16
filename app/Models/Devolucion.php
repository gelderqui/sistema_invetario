<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'venta_id',
        'usuario_id',
        'fecha',
        'estado',
        'total',
        'tipo_reintegro',
        'monto_reembolso_efectivo',
        'monto_aplicado_credito',
        'movimiento_caja_id',
        'movimiento_capital_id',
        'venta_credito_movimiento_id',
        'movimiento_caja_reversion_id',
        'movimiento_capital_reversion_id',
        'venta_credito_movimiento_reversion_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => 'string',
            'total' => 'decimal:4',
            'monto_reembolso_efectivo' => 'decimal:4',
            'monto_aplicado_credito' => 'decimal:4',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleDevolucion::class, 'devolucion_id');
    }
}
