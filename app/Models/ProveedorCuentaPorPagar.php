<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProveedorCuentaPorPagar extends Model
{
    protected $table = 'proveedor_cuentas_por_pagar';
    protected $fillable = ['proveedor_id','compra_id','compra_detalle_id','venta_id','venta_detalle_id','inventario_movimiento_id','cantidad','costo_unitario','monto_original','saldo_pendiente','estado','add_user'];
    protected function casts(): array { return ['cantidad'=>'decimal:4','costo_unitario'=>'decimal:4','monto_original'=>'decimal:4','saldo_pendiente'=>'decimal:4']; }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function aplicaciones(): HasMany { return $this->hasMany(ProveedorPagoAplicacion::class); }
}
