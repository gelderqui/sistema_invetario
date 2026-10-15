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
- `proveedor_cuentas_por_pagar`
- `proveedor_pagos`
- `proveedor_pago_aplicaciones`
- `gastos`

### 1.5 Caja y ventas

- `cajas`
- `movimientos_caja`
- `arqueos_caja`
- `ventas`
- `venta_detalles`
- `ventas_credito`
- `ventas_credito_movimientos`
- `devoluciones`
- `detalle_devoluciones`

### 1.6 Capital

- `capital_cuentas`
- `capital_movimientos`

### 1.7 Agente BI

- `agente_bi_deudas`
- `agente_bi_deuda_movimientos`
- `agente_bi_arqueos_caja_chica`
- `agente_bi_arqueo_detalles`
- `agente_bi_cuadres`

### 1.8 Activos

- `activos`

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
- `proveedores.nit` es opcional, no único y almacena el NIT normalizado del proveedor para su catálogo y búsqueda. El NIT que respalda una factura concreta permanece en `compras.nit_proveedor`.
- `compras.caja_id -> cajas.id` (M:1, nullable) identifica la caja POS que pagó una compra de contado. Solo se usa cuando `compras.metodo_pago = caja_pos`.
- `compra_detalles.compra_id -> compras.id` (1:N)
- `compra_detalles.producto_id -> productos.id` (M:1)
- `inventario_lotes.producto_id -> productos.id` (M:1)
- `inventario_lotes.compra_detalle_id -> compra_detalles.id` (M:1)
- `compras.tipo_compra` distingue contado, crédito de proveedor y consignación. `compras.es_consignacion` y `inventario_lotes.es_consignacion` identifican específicamente mercancía de terceros.
- `compras.factura_receptor`, `nit_receptor`, `nit_proveedor` y `serie_documento` conservan trazabilidad fiscal para compras de pago inmediato, crédito a proveedor y consignación. Con `factura_receptor = nit_empresa`, el lote recibido se clasifica como `con_factura`; con `CF`, recibo o sin documento se clasifica como `sin_factura`. Esta clasificación no altera el tipo de compra ni sus movimientos financieros.
- `inventario_movimientos.producto_id -> productos.id` (M:1)
- `inventario_movimientos.compra_id -> compras.id` (M:1)
- `inventario_movimientos.compra_detalle_id -> compra_detalles.id` (M:1)
- `inventario_movimientos.venta_id -> ventas.id` (M:1)
- `inventario_movimientos.venta_detalle_id -> venta_detalles.id` (M:1)
- `ajustes_inventario.producto_id -> productos.id` (M:1)
- `ajustes_inventario.motivo_id -> motivos_ajuste.id` (M:1)
- `ajustes_inventario.lote_id -> inventario_lotes.id` (M:1)
- `proveedor_cuentas_por_pagar.proveedor_id -> proveedores.id` (M:1)
- `proveedor_cuentas_por_pagar.compra_id -> compras.id` (M:1)
- `proveedor_cuentas_por_pagar.origen` distingue `compra_credito` y `consignacion_venta`.
- `proveedor_cuentas_por_pagar.venta_id -> ventas.id` (M:1; solo para consignación vendida)
- `proveedor_cuentas_por_pagar.inventario_movimiento_id -> inventario_movimientos.id` (1:1; solo para salida de venta consignada)
- `proveedor_pagos.proveedor_id -> proveedores.id` (M:1)
- `proveedor_pago_aplicaciones.proveedor_pago_id -> proveedor_pagos.id` (M:1)
- `proveedor_pago_aplicaciones.proveedor_cuenta_por_pagar_id -> proveedor_cuentas_por_pagar.id` (M:1)

## 2.4 Gastos

- `gastos.tipo_gasto_id -> tipos_gasto.id` (M:1)
- `gastos.usuario_id -> users.id` (M:1)

## 2.5 Ventas y devoluciones

- `ventas.cliente_id -> clientes.id` (M:1)
- `venta_detalles.venta_id -> ventas.id` (1:N)
- `venta_detalles.producto_id -> productos.id` (M:1)
- `ventas_credito.venta_id -> ventas.id` (1:1)
- `ventas_credito.cliente_id -> clientes.id` (M:1)
- `ventas_credito_movimientos.venta_credito_id -> ventas_credito.id` (1:N)
- `ventas_credito_movimientos.movimiento_caja_id -> movimientos_caja.id` (M:1, opcional; abonos y sus anulaciones)
- `ventas_credito_movimientos.movimiento_origen_id -> ventas_credito_movimientos.id` (M:1, opcional; enlaza una reversión con su movimiento original)
- `devoluciones.venta_id -> ventas.id` (M:1)
- `devoluciones.usuario_id -> users.id` (M:1)
- `devoluciones.movimiento_caja_id -> movimientos_caja.id` (M:1, opcional; reembolso)
- `devoluciones.venta_credito_movimiento_id -> ventas_credito_movimientos.id` (M:1, opcional; reducción de deuda)
- `devoluciones.movimiento_caja_reversion_id -> movimientos_caja.id` (M:1, opcional; anulación de reembolso)
- `devoluciones.venta_credito_movimiento_reversion_id -> ventas_credito_movimientos.id` (M:1, opcional; anulación de reducción de deuda)
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

## 2.8 Agente BI

- `agente_bi_deudas.usuario_id -> users.id` (M:1)
- `agente_bi_deuda_movimientos.agente_bi_deuda_id -> agente_bi_deudas.id` (1:N)
- `agente_bi_deuda_movimientos.usuario_id -> users.id` (M:1)
- `agente_bi_arqueos_caja_chica.usuario_id -> users.id` (M:1)
- `agente_bi_arqueo_detalles.agente_bi_arqueo_caja_chica_id -> agente_bi_arqueos_caja_chica.id` (1:N)
- `agente_bi_cuadres.agente_bi_arqueo_caja_chica_id -> agente_bi_arqueos_caja_chica.id` (M:1)
- `agente_bi_cuadres.usuario_id -> users.id` (M:1)

## 2.9 Activos

- `activos.usuario_id -> users.id` (M:1)

## 3. Notas de modelado

- Se prioriza no eliminar historicos criticos (ventas, compras, devoluciones).
- Para anulaciones se usa `estado` y movimientos de compensacion.
- `clientes.tipo_cliente` clasifica al cliente como `minorista` o `mayorista`; el valor por defecto es `minorista`.
- `productos.precio_venta` conserva el precio normal y `productos.precio_mayorista` es opcional. Al crear o editar un producto, vacío, `null` y `0` en el precio mayorista se guardan como `null`.
- El precio en venta se toma en backend: usa `precio_mayorista` solo para clientes mayoristas cuando es mayor que cero; en cualquier otro caso usa `precio_venta`.
- En caja, cierre puede depender del ultimo arqueo y umbral configurado.
- `ventas_credito` conserva el saldo vigente de una venta a crédito; su historial inmutable se registra en `ventas_credito_movimientos`.
- `devoluciones` guarda `tipo_reintegro`, monto reembolsado en efectivo y monto aplicado a crédito para que inventario, caja y cuentas por cobrar puedan revertirse de forma auditable.
- Una cuenta por pagar de compra a crédito nace al recibir la compra. Una de consignación nace únicamente al consumir un lote consignado en una venta; su recepción no crea deuda ni salida de capital.
- Los pagos a proveedor conservan sus aplicaciones para saber qué cargos fueron saldados, incluso cuando el pago fue parcial.

## 4. Indices de rendimiento adicionales

Migracion: `2026_03_17_120000_add_performance_indexes_for_historial_and_inventario.php`

- `ventas_user_fecha_id_idx` en `ventas(add_user, fecha_venta, id)`:
	- acelera historial de ventas por usuario y rango de fecha.
- `ventas_user_estado_fecha_id_idx` en `ventas(add_user, estado, fecha_venta, id)`:
	- acelera consultas de ventas por usuario, estado y fecha.
- `devoluciones_usuario_fecha_id_idx` en `devoluciones(usuario_id, fecha, id)`:
	- acelera auditorías de devoluciones por usuario y rango.
- `detalle_devoluciones_venta_detalle_idx` en `detalle_devoluciones(venta_detalle_id)`:
	- acelera sumatorias de cantidades devueltas por detalle de venta.
- `lotes_fifo_stock_idx` en `inventario_lotes(producto_id, cantidad_disponible, fecha_entrada)`:
	- acelera consumo FIFO de lotes con stock disponible.
- `inventario_movimientos_user_producto_idx` en `inventario_movimientos(add_user, producto_id)`:
	- acelera filtros de dashboard por usuario sobre productos movidos.
