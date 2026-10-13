<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorPagoAplicacion extends Model
{
    protected $table = 'proveedor_pago_aplicaciones';
    protected $fillable = ['proveedor_pago_id','proveedor_cuenta_por_pagar_id','monto'];
    protected function casts(): array { return ['monto'=>'decimal:4']; }
    public function pago(): BelongsTo { return $this->belongsTo(ProveedorPago::class, 'proveedor_pago_id'); }
    public function cuenta(): BelongsTo { return $this->belongsTo(ProveedorCuentaPorPagar::class, 'proveedor_cuenta_por_pagar_id'); }
}
