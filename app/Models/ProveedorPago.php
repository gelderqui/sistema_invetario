<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProveedorPago extends Model
{
    protected $table = 'proveedor_pagos';
    protected $fillable = ['proveedor_id','metodo_pago','monto','fecha','numero','observaciones','add_user'];
    protected function casts(): array { return ['monto'=>'decimal:4','fecha'=>'date']; }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function aplicaciones(): HasMany { return $this->hasMany(ProveedorPagoAplicacion::class); }
}
