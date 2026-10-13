<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'tipo_cliente',
        'nit',
        'email',
        'telefono',
        'direccion',
        'activo',
        'add_user',
        'mod_user',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'bool',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    public function creditos(): HasMany
    {
        return $this->hasMany(VentaCredito::class, 'cliente_id');
    }
}
