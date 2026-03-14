<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'cantidad',
        'costo_unitario',
        'subtotal',
        'precio_venta_sugerido',
        'precio_venta_aplicado',
        'fecha_caducidad',
        'peso',
        'cantidad_disponible',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'precio_venta_sugerido' => 'decimal:4',
            'precio_venta_aplicado' => 'decimal:4',
            'fecha_caducidad' => 'date',
            'peso' => 'decimal:4',
            'cantidad_disponible' => 'decimal:4',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
