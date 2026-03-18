<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'numero',
        'proveedor_id',
        'fecha_compra',
        'estado',
        'metodo_pago',
        'total',
        'tipo_documento',
        'numero_documento',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'metodo_pago' => 'string',
            'total' => 'decimal:4',
            'tipo_documento' => 'string',
            'numero_documento' => 'string',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'compra_id');
    }
}
