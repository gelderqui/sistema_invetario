<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompraDetalle extends Model
{
    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'cantidad',
        'unidad_medida',
        'costo_unitario',
        'subtotal',
        'precio_venta_sugerido',
        'precio_venta_aplicado',
        'fecha_caducidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'              => 'decimal:4',
            'unidad_medida'         => 'string',
            'costo_unitario'        => 'decimal:2',
            'subtotal'              => 'decimal:2',
            'precio_venta_sugerido' => 'decimal:2',
            'precio_venta_aplicado' => 'decimal:2',
            'fecha_caducidad'       => 'date',
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

    public function inventarioLote(): HasOne
    {
        return $this->hasOne(InventarioLote::class, 'compra_detalle_id');
    }
}
