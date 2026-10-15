<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioLote extends Model
{
    protected $table = 'inventario_lotes';

    protected $fillable = [
        'producto_id',
        'compra_detalle_id',
        'es_consignacion',
        'respaldo_fiscal',
        'cantidad_inicial',
        'cantidad_disponible',
        'costo_unitario',
        'fecha_vencimiento',
        'fecha_entrada',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_inicial'    => 'decimal:4',
            'es_consignacion'     => 'bool',
            'respaldo_fiscal'     => 'string',
            'cantidad_disponible' => 'decimal:4',
            'costo_unitario'      => 'decimal:2',
            'fecha_vencimiento'   => 'date',
            'fecha_entrada'       => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }
}
