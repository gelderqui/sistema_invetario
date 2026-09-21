<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaCreditoMovimiento extends Model
{
    protected $table = 'ventas_credito_movimientos';

    protected $fillable = [
        'venta_credito_id', 'tipo', 'monto', 'saldo_anterior', 'saldo_posterior',
        'fecha', 'metodo_pago', 'descripcion', 'usuario_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'monto' => 'decimal:4', 'saldo_anterior' => 'decimal:4', 'saldo_posterior' => 'decimal:4'];
    }

    public function credito(): BelongsTo { return $this->belongsTo(VentaCredito::class, 'venta_credito_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id')->withTrashed(); }
}
