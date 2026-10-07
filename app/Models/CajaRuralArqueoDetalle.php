<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaRuralArqueoDetalle extends Model
{
    protected $table = 'caja_rural_arqueo_detalles';

    protected $fillable = [
        'caja_rural_arqueo_caja_chica_id',
        'tipo',
        'denominacion',
        'unidades_por_paquete',
        'cantidad',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'denominacion' => 'decimal:2',
            'cantidad' => 'decimal:2',
            'subtotal' => 'decimal:4',
        ];
    }

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(CajaRuralArqueoCajaChica::class, 'caja_rural_arqueo_caja_chica_id');
    }
}

