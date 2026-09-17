<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activo extends Model
{
    protected $fillable = ['codigo', 'nombre', 'categoria', 'ubicacion', 'marca', 'modelo', 'numero_serie', 'fecha_compra', 'costo_compra', 'estado', 'descripcion', 'usuario_id', 'activo'];

    protected function casts(): array
    {
        return ['fecha_compra' => 'date', 'costo_compra' => 'decimal:4', 'activo' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
