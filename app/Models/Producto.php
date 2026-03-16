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
        'nombre',
        'codigo_barra',
        'palabras_clave',
        'precio_venta',
        'costo_promedio',
        'stock_actual',
        'stock_minimo',
        'unidad_medida_id',
        'control_vencimiento',
        'dias_alerta_vencimiento',
        'peso_referencial',
        'activo',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'activo'              => 'bool',
            'control_vencimiento' => 'bool',
            'dias_alerta_vencimiento' => 'integer',
            'precio_venta'        => 'decimal:4',
            'costo_promedio'      => 'decimal:4',
            'stock_actual'        => 'decimal:4',
            'stock_minimo'        => 'decimal:4',
            'peso_referencial'    => 'decimal:4',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(ProductoUnidadMedida::class, 'unidad_medida_id');
    }

    public function inventarioLotes(): HasMany
    {
        return $this->hasMany(InventarioLote::class, 'producto_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'producto_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id');
    }

    public function ajustesInventario(): HasMany
    {
        return $this->hasMany(AjusteInventario::class, 'producto_id');
    }
}
