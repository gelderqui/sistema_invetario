<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAjaxRequest;
use App\Models\Categoria;
use App\Models\CompraDetalle;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraPricingRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadMedida $unidadMedida;

    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            CheckPermission::class,
            EnsureAjaxRequest::class,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'code' => 'admin',
            'activo' => true,
            'is_system' => true,
        ]);

        $this->user = User::factory()->create([
            'username' => 'compras-tester',
            'role_id' => $role->id,
            'activo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum');

        $this->unidadMedida = UnidadMedida::query()->create([
            'nombre' => 'Unidad',
            'abreviatura' => 'und',
            'activo' => true,
        ]);

        $this->categoria = Categoria::query()->create([
            'nombre' => 'Categoria prueba',
            'activo' => true,
            'add_user' => $this->user->id,
        ]);
    }

    public function test_catalogs_exposes_purchase_margin_percentage(): void
    {
        Configuracion::query()->updateOrCreate([
            'codigo' => 'porcentaje_utilidad_compra',
        ], [
            'descripcion' => 'Margen de compra',
            'value' => '45',
            'activo' => true,
        ]);

        $this->getJson('/api/compras/get/catalogs')
            ->assertOk()
            ->assertJsonPath('data.porcentaje_utilidad_compra', 45);
    }

    public function test_purchase_rejects_decimal_quantities(): void
    {
        $proveedor = $this->createProveedor();
        $producto = $this->createProducto();

        $this->postJson('/api/compras/store', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'items' => [
                [
                    'categoria_id' => $this->categoria->id,
                    'producto_id' => $producto->id,
                    'cantidad' => 1.5,
                    'costo_unitario' => 10,
                    'precio_venta' => 12,
                    'fecha_caducidad' => now()->addMonth()->toDateString(),
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.cantidad']);
    }

    public function test_purchase_uses_configured_margin_for_suggested_sale_price(): void
    {
        Configuracion::query()->updateOrCreate([
            'codigo' => 'porcentaje_utilidad_compra',
        ], [
            'descripcion' => 'Margen de compra',
            'value' => '25',
            'activo' => true,
        ]);

        $proveedor = $this->createProveedor();
        $producto = $this->createProducto();

        $this->postJson('/api/compras/store', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'items' => [
                [
                    'categoria_id' => $this->categoria->id,
                    'producto_id' => $producto->id,
                    'cantidad' => 2,
                    'costo_unitario' => 10,
                    'precio_venta' => 12.5,
                    'fecha_caducidad' => now()->addMonth()->toDateString(),
                ],
            ],
        ])
            ->assertCreated();

        $this->assertDatabaseHas('compra_detalles', [
            'producto_id' => $producto->id,
            'precio_venta_sugerido' => '12.5000',
            'precio_venta_aplicado' => '12.5000',
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'precio_venta' => '12.5000',
            'stock_actual' => '2.0000',
        ]);
    }

    public function test_purchase_allows_manual_sale_price_override(): void
    {
        Configuracion::query()->updateOrCreate([
            'codigo' => 'porcentaje_utilidad_compra',
        ], [
            'descripcion' => 'Margen de compra',
            'value' => '25',
            'activo' => true,
        ]);

        $proveedor = $this->createProveedor();
        $producto = $this->createProducto();

        $response = $this->postJson('/api/compras/store', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'items' => [
                [
                    'categoria_id' => $this->categoria->id,
                    'producto_id' => $producto->id,
                    'cantidad' => 2,
                    'costo_unitario' => 10,
                    'precio_venta' => 14,
                    'fecha_caducidad' => now()->addMonth()->toDateString(),
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('alerts'));

        $detalle = CompraDetalle::query()->where('producto_id', $producto->id)->firstOrFail();

        $this->assertSame('12.5000', number_format((float) $detalle->precio_venta_sugerido, 4, '.', ''));
        $this->assertSame('14.0000', number_format((float) $detalle->precio_venta_aplicado, 4, '.', ''));
    }

    public function test_purchase_rejects_duplicate_products_in_items(): void
    {
        $proveedor = $this->createProveedor();
        $producto = $this->createProducto();

        $this->postJson('/api/compras/store', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 50,
                    'costo_unitario' => 10,
                    'precio_venta' => 12,
                    'fecha_caducidad' => now()->addMonth()->toDateString(),
                ],
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 50,
                    'costo_unitario' => 10,
                    'precio_venta' => 12,
                    'fecha_caducidad' => now()->addMonth()->toDateString(),
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.producto_id']);
    }

    private function createProveedor(): Proveedor
    {
        return Proveedor::query()->create([
            'nombre' => 'Proveedor prueba',
            'activo' => true,
            'add_user' => $this->user->id,
        ]);
    }

    private function createProducto(): Producto
    {
        static $sequence = 1;

        return Producto::query()->create([
            'categoria_id' => $this->categoria->id,
            'nombre' => 'Producto compra '.$sequence,
            'codigo_barra' => 'CP'.$sequence++,
            'precio_venta' => 0,
            'costo_promedio' => 0,
            'stock_actual' => 0,
            'stock_minimo' => 1,
            'unidad_medida_id' => $this->unidadMedida->id,
            'control_vencimiento' => false,
            'dias_alerta_vencimiento' => 15,
            'activo' => true,
            'add_user' => $this->user->id,
        ]);
    }
}