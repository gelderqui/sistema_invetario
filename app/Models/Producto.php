<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'proveedor_id',
        'nombre',
        'codigo',
        'codigo_barra',
        'detalle',
        'palabras_clave',
        'precio_venta',
        'costo_promedio',
        'stock_actual',
        'stock_minimo',
        'unidad_medida',
        'peso_referencial',
        'activo',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'bool',
            'precio_venta' => 'decimal:4',
            'costo_promedio' => 'decimal:4',
            'stock_actual' => 'decimal:4',
            'stock_minimo' => 'decimal:4',
            'peso_referencial' => 'decimal:4',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id');
    }
}
