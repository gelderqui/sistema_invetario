<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaCredito extends Model
{
    protected $table = 'ventas_credito';

    protected $fillable = [
        'venta_id', 'cliente_id', 'fecha_vencimiento', 'monto_original',
        'saldo_pendiente', 'estado', 'observaciones', 'add_user', 'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'monto_original' => 'decimal:4',
            'saldo_pendiente' => 'decimal:4',
        ];
    }

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function movimientos(): HasMany { return $this->hasMany(VentaCreditoMovimiento::class); }
}
