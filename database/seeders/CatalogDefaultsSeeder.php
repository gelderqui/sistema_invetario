<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoUnidadMedida;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class CatalogDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::query()->updateOrCreate(
            ['nombre' => 'Consumidor final'],
            [
                'nit' => 'CF',
                'activo' => true,
            ]
        );

        Proveedor::query()->updateOrCreate(
            ['nombre' => 'Proveedores varios'],
            [
                'activo' => true,
            ]
        );

        $categoriaGeneral = Categoria::query()->updateOrCreate(
            ['nombre' => 'General'],
            [
                'descripcion' => 'Categoria por defecto del sistema.',
                'activo' => true,
            ]
        );

        $unidadUnidad = ProductoUnidadMedida::query()->where('abreviatura', 'und')->first();

        Producto::query()->updateOrCreate(
            ['nombre' => 'Producto prueba'],
            [
                'categoria_id'       => $categoriaGeneral->id,
                'codigo_barra'       => null,
                'palabras_clave'     => 'prueba,general',
                'precio_venta'       => 0,
                'costo_promedio'     => 0,
                'stock_actual'       => 0,
                'stock_minimo'       => 0,
                'unidad_medida_id'   => $unidadUnidad?->id,
                'control_vencimiento' => false,
                'peso_referencial'   => 0,
                'activo'             => true,
            ]
        );
    }
}
