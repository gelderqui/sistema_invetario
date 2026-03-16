<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAjaxRequest;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\ProductoUnidadMedida;
use App\Models\Role;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoActivationRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProductoUnidadMedida $unidadMedida;

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
            'username' => 'tester',
            'role_id' => $role->id,
            'activo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum');

        $this->unidadMedida = ProductoUnidadMedida::query()->create([
            'nombre' => 'Unidad',
            'abreviatura' => 'und',
            'activo' => true,
        ]);
    }

    public function test_compras_catalog_only_includes_active_products(): void
    {
        $productoActivo = $this->createProducto(['activo' => true, 'nombre' => 'Producto activo compras']);
        $productoInactivo = $this->createProducto(['activo' => false, 'nombre' => 'Producto inactivo compras']);

        $response = $this->getJson('/api/compras/get/catalogs');

        $response->assertOk();
        $ids = collect($response->json('data.productos'))->pluck('id')->all();

        $this->assertContains($productoActivo->id, $ids);
        $this->assertNotContains($productoInactivo->id, $ids);
    }

    public function test_ventas_catalog_only_includes_active_products(): void
    {
        $productoActivo = $this->createProducto(['activo' => true, 'nombre' => 'Producto activo ventas']);
        $productoInactivo = $this->createProducto(['activo' => false, 'nombre' => 'Producto inactivo ventas']);

        $response = $this->getJson('/api/ventas/get/catalogs');

        $response->assertOk();
        $ids = collect($response->json('data.productos'))->pluck('id')->all();

        $this->assertContains($productoActivo->id, $ids);
        $this->assertNotContains($productoInactivo->id, $ids);
    }

    public function test_reactivated_product_returns_to_purchase_and_sales_catalogs(): void
    {
        $producto = $this->createProducto([
            'activo' => true,
            'nombre' => 'Producto reactivo',
        ]);

        $this->patchJson("/api/productos/toggle/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $comprasTrasDesactivar = collect($this->getJson('/api/compras/get/catalogs')->json('data.productos'))->pluck('id')->all();
        $ventasTrasDesactivar = collect($this->getJson('/api/ventas/get/catalogs')->json('data.productos'))->pluck('id')->all();

        $this->assertNotContains($producto->id, $comprasTrasDesactivar);
        $this->assertNotContains($producto->id, $ventasTrasDesactivar);

        $this->patchJson("/api/productos/toggle/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $comprasTrasActivar = collect($this->getJson('/api/compras/get/catalogs')->json('data.productos'))->pluck('id')->all();
        $ventasTrasActivar = collect($this->getJson('/api/ventas/get/catalogs')->json('data.productos'))->pluck('id')->all();

        $this->assertContains($producto->id, $comprasTrasActivar);
        $this->assertContains($producto->id, $ventasTrasActivar);
    }

    public function test_product_cannot_be_deleted_if_it_has_purchase_history(): void
    {
        $producto = $this->createProducto(['nombre' => 'Producto con compra']);

        $compra = Compra::query()->create([
            'numero' => 'CMP-TEST-001',
            'proveedor_id' => $this->createProveedorId(),
            'fecha_compra' => now()->toDateString(),
            'estado' => 'activo',
            'total' => 25,
            'add_user' => $this->user->id,
        ]);

        CompraDetalle::query()->create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'unidad_medida' => 'und',
            'costo_unitario' => 25,
            'subtotal' => 25,
        ]);

        $this->deleteJson("/api/productos/destroy/{$producto->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar este producto porque ya tiene compras o ventas registradas.');

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_product_cannot_be_deleted_if_it_has_sales_history(): void
    {
        $producto = $this->createProducto(['nombre' => 'Producto con venta']);

        $venta = Venta::query()->create([
            'numero' => 'VTA-TEST-001',
            'fecha_venta' => now()->toDateString(),
            'estado' => 'activo',
            'metodo_pago' => 'tarjeta',
            'subtotal' => 30,
            'descuento' => 0,
            'total' => 30,
            'add_user' => $this->user->id,
        ]);

        VentaDetalle::query()->create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'unidad_medida' => 'und',
            'precio_unitario' => 30,
            'subtotal' => 30,
        ]);

        $this->deleteJson("/api/productos/destroy/{$producto->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar este producto porque ya tiene compras o ventas registradas.');

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_product_can_be_deleted_when_it_has_no_purchase_or_sales_history(): void
    {
        $producto = $this->createProducto(['nombre' => 'Producto sin historial']);

        $this->deleteJson("/api/productos/destroy/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Producto eliminado correctamente.');

        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    }

    private function createProducto(array $overrides = []): Producto
    {
        static $sequence = 1;

        $producto = Producto::query()->create(array_merge([
            'nombre' => 'Producto '.$sequence,
            'codigo_barra' => 'CB'.$sequence,
            'precio_venta' => 10,
            'costo_promedio' => 7,
            'stock_actual' => 5,
            'stock_minimo' => 1,
            'unidad_medida_id' => $this->unidadMedida->id,
            'control_vencimiento' => false,
            'dias_alerta_vencimiento' => 15,
            'activo' => true,
            'add_user' => $this->user->id,
        ], $overrides));

        $sequence++;

        return $producto;
    }

    private function createProveedorId(): int
    {
        static $sequence = 1;

        return \App\Models\Proveedor::query()->create([
            'nombre' => 'Proveedor '.$sequence++,
            'activo' => true,
            'add_user' => $this->user->id,
        ])->id;
    }
}