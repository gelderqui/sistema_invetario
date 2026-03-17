# README Base de Datos

Este documento describe las tablas actuales y sus relaciones principales.

## 1. Tablas por dominio

### 1.1 Default Laravel

- `migrations`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `password_reset_tokens`
- `sessions`

### 1.2 Seguridad y configuracion

- `users`
- `roles`
- `permissions`
- `permission_role`
- `configuraciones`

### 1.3 Catalogo

- `categorias`
- `productos_unidad_medida`
- `productos`
- `tipos_gasto`
- `proveedores`
- `clientes`

### 1.4 Operaciones (compras/inventario)

- `compras`
- `compra_detalles`
- `inventario_lotes`
- `inventario_movimientos`
- `motivos_ajuste`
- `ajustes_inventario`
- `gastos`

### 1.5 Caja y ventas

- `cajas`
- `movimientos_caja`
- `arqueos_caja`
- `ventas`
- `venta_detalles`
- `devoluciones`
- `detalle_devoluciones`

### 1.6 Capital

- `capital_cuentas`
- `capital_movimientos`

## 2. Relaciones principales

## 2.1 Seguridad

- `users.role_id -> roles.id` (M:1)
- `permission_role.permission_id -> permissions.id` (M:M roles-permisos)
- `permission_role.role_id -> roles.id` (M:M roles-permisos)

## 2.2 Catalogo

- `productos.categoria_id -> categorias.id` (M:1)
- `productos.unidad_medida_id -> productos_unidad_medida.id` (M:1)

## 2.3 Compras e inventario

- `compras.proveedor_id -> proveedores.id` (M:1)
- `compra_detalles.compra_id -> compras.id` (1:N)
- `compra_detalles.producto_id -> productos.id` (M:1)
- `inventario_lotes.producto_id -> productos.id` (M:1)
- `inventario_lotes.compra_detalle_id -> compra_detalles.id` (M:1)
- `inventario_movimientos.producto_id -> productos.id` (M:1)
- `inventario_movimientos.compra_id -> compras.id` (M:1)
- `inventario_movimientos.compra_detalle_id -> compra_detalles.id` (M:1)
- `inventario_movimientos.venta_id -> ventas.id` (M:1)
- `inventario_movimientos.venta_detalle_id -> venta_detalles.id` (M:1)
- `ajustes_inventario.producto_id -> productos.id` (M:1)
- `ajustes_inventario.motivo_id -> motivos_ajuste.id` (M:1)
- `ajustes_inventario.lote_id -> inventario_lotes.id` (M:1)

## 2.4 Gastos

- `gastos.tipo_gasto_id -> tipos_gasto.id` (M:1)
- `gastos.usuario_id -> users.id` (M:1)

## 2.5 Ventas y devoluciones

- `ventas.cliente_id -> clientes.id` (M:1)
- `venta_detalles.venta_id -> ventas.id` (1:N)
- `venta_detalles.producto_id -> productos.id` (M:1)
- `devoluciones.venta_id -> ventas.id` (M:1)
- `devoluciones.usuario_id -> users.id` (M:1)
- `detalle_devoluciones.devolucion_id -> devoluciones.id` (1:N)
- `detalle_devoluciones.venta_detalle_id -> venta_detalles.id` (M:1)
- `detalle_devoluciones.producto_id -> productos.id` (M:1)

## 2.6 Caja

- `cajas.usuario_id -> users.id` (M:1)
- `movimientos_caja.caja_id -> cajas.id` (1:N)
- `movimientos_caja.usuario_id -> users.id` (M:1)
- `arqueos_caja.caja_id -> cajas.id` (1:N)
- `arqueos_caja.usuario_id -> users.id` (M:1)

## 2.7 Capital

- `capital_movimientos.cuenta_origen_id -> capital_cuentas.id` (M:1)
- `capital_movimientos.cuenta_destino_id -> capital_cuentas.id` (M:1)
- `capital_movimientos.usuario_id -> users.id` (M:1)

## 3. Notas de modelado

- Se prioriza no eliminar historicos criticos (ventas, compras, devoluciones).
- Para anulaciones se usa `estado` y movimientos de compensacion.
- El precio en venta se toma en backend desde `productos.precio_venta`.
- En caja, cierre puede depender del ultimo arqueo y umbral configurado.

## 4. Indices de rendimiento adicionales

Migracion: `2026_03_17_120000_add_performance_indexes_for_historial_and_inventario.php`

- `ventas_user_fecha_id_idx` en `ventas(add_user, fecha_venta, id)`:
	- acelera historial de ventas por usuario y rango de fecha.
- `ventas_user_estado_fecha_id_idx` en `ventas(add_user, estado, fecha_venta, id)`:
	- acelera catalogos de devolucion y reportes filtrados por usuario + estado.
- `devoluciones_usuario_fecha_id_idx` en `devoluciones(usuario_id, fecha, id)`:
	- acelera historial de devoluciones por usuario y rango.
- `detalle_devoluciones_venta_detalle_idx` en `detalle_devoluciones(venta_detalle_id)`:
	- acelera sumatorias de cantidades devueltas por detalle de venta.
- `lotes_fifo_stock_idx` en `inventario_lotes(producto_id, cantidad_disponible, fecha_entrada)`:
	- acelera consumo FIFO de lotes con stock disponible.
- `inventario_movimientos_user_producto_idx` en `inventario_movimientos(add_user, producto_id)`:
	- acelera filtros de dashboard por usuario sobre productos movidos.
