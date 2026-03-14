<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'compra_id',
        'compra_detalle_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'costo_unitario',
        'referencia',
        'nota',
        'add_user',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'stock_anterior' => 'decimal:4',
            'stock_nuevo' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }
}
