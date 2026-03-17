<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'numero',
        'cliente_id',
        'fecha_venta',
        'estado',
        'metodo_pago',
        'subtotal',
        'descuento',
        'total',
        'monto_recibido',
        'cambio',
        'observaciones',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'estado' => 'string',
            'subtotal' => 'decimal:4',
            'descuento' => 'decimal:4',
            'total' => 'decimal:4',
            'monto_recibido' => 'decimal:4',
            'cambio' => 'decimal:4',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'add_user')->withTrashed();
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'venta_id');
    }
}
