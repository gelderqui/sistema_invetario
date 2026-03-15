<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'usuario_id',
        'fecha_apertura',
        'monto_apertura',
        'estado',
        'fecha_cierre',
        'total_ventas',
        'total_gastos',
        'total_compras',
        'total_ajustes',
        'monto_sistema_final',
        'monto_contado_final',
        'diferencia',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'monto_apertura' => 'decimal:4',
            'total_ventas' => 'decimal:4',
            'total_gastos' => 'decimal:4',
            'total_compras' => 'decimal:4',
            'total_ajustes' => 'decimal:4',
            'monto_sistema_final' => 'decimal:4',
            'monto_contado_final' => 'decimal:4',
            'diferencia' => 'decimal:4',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'caja_id');
    }

    public function arqueos(): HasMany
    {
        return $this->hasMany(ArqueoCaja::class, 'caja_id');
    }
}
