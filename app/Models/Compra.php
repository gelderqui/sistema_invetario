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
        'caja_id',
        'tipo_compra',
        'es_consignacion',
        'total',
        'tipo_documento',
        'numero_documento',
        'factura_receptor',
        'nit_receptor',
        'nit_proveedor',
        'serie_documento',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'metodo_pago' => 'string',
            'tipo_compra' => 'string',
            'es_consignacion' => 'bool',
            'total' => 'decimal:2',
            'tipo_documento' => 'string',
            'numero_documento' => 'string',
            'factura_receptor' => 'string',
            'nit_receptor' => 'string',
            'nit_proveedor' => 'string',
            'serie_documento' => 'string',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
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
