# Flujos Criticos

Este documento describe flujos operativos punta a punta que impactan dinero, inventario y auditoria.

## 1. Flujo maestro de operacion diaria

1. Configuracion inicial: inversion inicial, categorias, productos y proveedores.
2. Carga de inventario inicial en `Inventario inicial` (sin compras).
3. Registrar compras reales para reposicion.
4. Abrir caja del turno.
5. Registrar ventas en POS.
6. Registrar movimientos y arqueos de caja durante jornada.
7. Cerrar caja.
8. Verificar impactos en capital y reportes (estado general + utilidad).

## 1.1 Flujo de arranque (primera vez)

1. Registrar primer ingreso de capital (se considera capital inicial).
2. Cargar saldo financiero de cuentas de capital (caja_general/banco) segun operacion.
3. Registrar inventario inicial por producto con cantidad y costo.
4. Confirmar en Reportes que `total_negocio` refleja efectivo + inventario.

## 2. Compra -> inventario

1. Se registra compra con proveedor activo.
2. Se crean `compra_detalles`.
3. Se crean lotes en `inventario_lotes`.
4. Se registran entradas en `inventario_movimientos`.
5. Se recalculan datos de producto (stock/costos/precio sugerido segun regla vigente).

## 2.1 Inventario inicial -> inventario (sin compra)

1. Se registra carga inicial en `Inventario inicial`.
2. Se crea lote con `compra_detalle_id = null`.
3. Se registra movimiento `inventario_inicial` en inventario.
4. Se actualiza stock y costos del producto.
5. No se crea compra ni se afecta historial de proveedores.

## 2.1.1 Documento fiscal de compra -> clasificación del lote

1. En una compra de contado, crédito o consignación se puede registrar sin documento, recibo o factura.
2. Si es factura, se indica si fue emitida como `CF` o a `nit_empresa`.
3. Una factura a `nit_empresa` requiere NIT de proveedor y clasifica el lote como `con_factura`.
4. CF, recibo o ausencia de documento clasifican el lote como `sin_factura`.
5. Esta clasificación se usa para filtros y exportaciones de Compras e Inventario; no crea ni cancela pagos por sí misma.

## 2.1.2 Compra pagada desde caja POS -> inventario y caja del turno

1. El usuario debe tener acceso a Compras y Ventas, además de una caja POS propia en estado abierta.
2. En una compra de contado selecciona **Efectivo de mi caja POS abierta** como método de pago.
3. Se crean compra, detalles, lotes y movimientos de inventario como en cualquier compra de contado.
4. La compra conserva el identificador de la caja origen en `compras.caja_id`.
5. Se crea un movimiento `compra` negativo en `movimientos_caja`; no se descuenta Caja general ni Banco directamente.
6. Si se anula antes del cierre, se revierte inventario y se registra `anulacion_compra` positivo en la misma caja POS.
7. Si se anula después del cierre, la caja cerrada no se modifica; se revierte inventario y se registra un ingreso de capital `anulacion_compra` en Caja general.

## 2.2 Compra a crédito -> inventario propio + cuenta por pagar

1. En Compras se selecciona **Compra a crédito**.
2. Se crean compra, detalles, lotes y movimientos de entrada como en una compra de contado.
3. Los lotes son propios (`es_consignacion = false`), por lo que la mercancía pertenece al negocio desde la recepción.
4. No se registra salida de Caja general ni Banco.
5. Se crea una cuenta por pagar al proveedor por el total de la compra.
6. Si el proveedor entrega factura a nombre del NIT de la empresa, el lote queda clasificado como inventario con factura.

## 2.3 Compra en consignación -> inventario (sin salida de efectivo)

1. La tienda habilita `habilitar_consignacion` en Configuraciones.
2. En Compras se selecciona `Consignación` y se elige un proveedor activo.
3. Se crean `compras`, `compra_detalles`, lotes y movimientos de entrada como en una compra normal.
4. La compra y su lote quedan marcados como consignación.
5. No se registra salida en `capital_movimientos`; la mercancía sigue siendo propiedad del proveedor hasta su venta.
6. La factura recibida al ingreso puede registrarse como CF o a nombre del NIT de la empresa; en este último caso el lote queda clasificado como inventario con factura.

## 3. Venta -> inventario -> caja o cuenta por cobrar

1. Se valida que el usuario tenga caja abierta, cliente (si aplica) y productos activos con stock.
2. Precio unitario se toma desde `productos.precio_venta`.
3. Se crea `venta` y `venta_detalles`.
4. Se descargan lotes FIFO en inventario.
5. Se crean movimientos de inventario (`salida_venta`).
6. Por cada lote consignado consumido, se crea un cargo en `proveedor_cuentas_por_pagar` por la cantidad vendida y el costo pactado.

## 3.1 Pago a proveedor -> capital

1. En `Cuentas por pagar` se seleccionan cargos pendientes de un solo proveedor.
2. Se registra un pago total o parcial desde `caja_general` o `banco`.
3. Se guardan `proveedor_pagos` y sus aplicaciones a cada cargo.
4. El saldo de cada cargo cambia a `parcial` o `pagada`.
5. Se registra una salida de capital `pago_proveedor`.
6. Si es efectivo, se registra movimiento automatico en caja.
7. Si es crédito, se crea una cuenta por cobrar y su movimiento inicial.

## 3.1 Abono, anulación de abono e incobrable

1. Un abono requiere una caja abierta del usuario que lo recibe.
2. El abono disminuye la deuda y genera un ingreso `abono_venta_credito` en caja.
3. Solo se puede anular el último abono vigente, desde la misma caja abierta y por el mismo usuario.
4. La anulación repone la deuda y crea `anulacion_abono_venta_credito` como salida de caja.
5. Una cuenta activa con saldo puede marcarse como incobrable; el saldo se cierra y se registra una pérdida de utilidad, sin movimiento de caja.

## 4. Arqueo y cierre de caja

1. Arqueo calcula diferencia entre sistema y contado, guarda evidencia.
2. Cierre toma monto contado del ultimo arqueo (si existe).
3. Cierre valida umbral de faltante (`caja_alerta_faltante_monto`).
4. Si excede umbral, no permite cerrar.
5. Si cierra, consolida totales y registra movimiento de capital correspondiente.

## 5. Devolucion/anulacion

1. Se valida politica por fecha y estado.
2. No se borra historico; se marca/anula y compensa.
3. Se repone inventario con trazabilidad de los lotes originales.
4. Venta al contado: el reembolso sale de la caja POS del usuario si la caja original de la venta sigue abierta; si esta cerró, sale de Caja general.
5. Venta a crédito: se reduce primero la deuda; si el retorno excede el saldo, el excedente se reembolsa desde caja POS o Caja general si los abonos vigentes ya pasaron por una caja cerrada.
6. La devolución conserva los vínculos a sus movimientos de caja POS, capital y crédito.
7. Al anularla, se retira de nuevo el inventario y se revierten deuda y reembolso. Una caja POS cerrada no se modifica: el reintegro va a Caja general. Si la caja del reembolso sigue abierta, la reversión queda en esa misma caja y la realiza su usuario.
8. Si la venta contiene mercancía consignada sin pagos aplicados al proveedor, la devolución reduce o anula el cargo respectivo. Al anular la devolución se restituyen cantidad, monto, saldo y estado de cada cargo afectado. Si después se aplicó cualquier pago, toda la reversión se bloquea para conservar caja, inventario y deuda coordinados.

### 5.1 Importe neto de una devolución con descuento

1. Se bloquea la venta durante la operación y se toman sus detalles y total neto originales, sin usar el precio actual del producto ni importes enviados por frontend.
2. `ImporteDevolucionService` reparte el total neto proporcionalmente entre los subtotales originales, usando centavos enteros y asignación determinista de los residuos.
3. Para cada detalle se descuentan las cantidades y subtotales de devoluciones activas anteriores. La devolución parcial se calcula proporcionalmente sobre el saldo restante; la última cantidad completa los centavos pendientes.
4. Se valida el límite del detalle y el de toda la venta antes de confirmar. Se rechazan detalles repetidos, cantidades excesivas y nuevos reembolsos sobre un historial que ya excedió su importe neto.
5. El subtotal neto calculado se aplica a la caja o a la deuda y su excedente, siguiendo el flujo financiero habitual. El costo del lote y la reposición de inventario no se calculan desde el precio de devolución.
6. Anular la devolución conserva el registro, revierte su efecto financiero y hace que sus unidades e importe vuelvan a estar disponibles para otra devolución.

Ejemplo: tres unidades a Q20 menos Q15 de descuento equivalen a Q45 netos. Una devolución completa vale Q45, y tres devoluciones de una unidad valen Q15 cada una. Si Q1.00 neto debe repartirse entre tres unidades, las parciales pueden ser Q0.33, Q0.33 y Q0.34, sin exceder Q1.00.

### 5.2 Anulación de venta en efectivo después del cierre

1. Solo el usuario que registró la venta puede anularla si está activa y es del mismo día.
2. Se bloquea la venta y se comprueba el ingreso original en caja. Si su caja sigue abierta, la salida se asienta en esa misma caja; si cerró, se debita Caja general mediante un movimiento `anulacion_venta` ligado a la venta.
3. Si no existe ingreso trazable, Caja general activa o saldo suficiente, falla toda la transacción: inventario, estado, cuentas y dinero permanecen sin cambios.
4. El arqueo, el cierre y los movimientos de la caja cerrada siguen intactos; una segunda solicitud no crea otra salida.

### 5.3 Costos en Reportes y Dashboard

1. El ingreso y costo de una venta se incluyen únicamente mientras la venta está activa, usando `fecha_venta` para el rango consultado.
2. El ingreso revertido y costo recuperado de una devolución se incluyen únicamente mientras la devolución está activa, usando la fecha de la devolución.
3. Los movimientos históricos de inventario no se borran. `inventario_movimientos.devolucion_id` permite identificar qué devolución produjo cada reposición o reversión.
4. Anular una venta o crear y después anular una devolución deja la utilidad del periodo como estaba antes de esa operación. El servicio compartido evita diferencias entre Reportes y Dashboard.

## 6. Flujo de hora del sistema

1. Backend expone hora base del servidor.
2. Frontend sincroniza al cargar sesion.
3. Frontend anima reloj localmente.
4. Frontend resincroniza cada 30 minutos para corregir desfase.

## 7. Controles de integridad

- Toda regla critica debe validarse en backend.
- No confiar en restricciones solo de frontend.
- Cambios en estos flujos requieren actualizacion de:
  - `README_CONTEXT.md`
  - `README_REGLAS_INQUEBRANTABLES.md`
  - `README_BASE_DATOS.md` (si cambia modelo)
