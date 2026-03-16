<?php

namespace Database\Seeders;

use App\Models\ProductoUnidadMedida;
use Illuminate\Database\Seeder;

class ProductoUnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['nombre' => 'Unidad',      'abreviatura' => 'und'],
            ['nombre' => 'Libra',       'abreviatura' => 'lb'],
            ['nombre' => 'Kilogramo',   'abreviatura' => 'kg'],
            ['nombre' => 'Gramo',       'abreviatura' => 'g'],
            ['nombre' => 'Onza',        'abreviatura' => 'oz'],
            ['nombre' => 'Quintal',     'abreviatura' => 'qq'],
            ['nombre' => 'Litro',       'abreviatura' => 'lt'],
            ['nombre' => 'Mililitro',   'abreviatura' => 'ml'],
            ['nombre' => 'Galon',       'abreviatura' => 'gal'],
            ['nombre' => 'Caja',        'abreviatura' => 'cja'],
            ['nombre' => 'Paquete',     'abreviatura' => 'pqt'],
            ['nombre' => 'Docena',      'abreviatura' => 'doc'],
            ['nombre' => 'Botella',     'abreviatura' => 'bot'],
            ['nombre' => 'Lata',        'abreviatura' => 'lta'],
            ['nombre' => 'Bolsa',       'abreviatura' => 'bol'],
        ];

        foreach ($items as $item) {
            ProductoUnidadMedida::query()->updateOrCreate(
                ['abreviatura' => $item['abreviatura']],
                [
                    'nombre' => $item['nombre'],
                    'activo' => true,
                ]
            );
        }
    }
}
