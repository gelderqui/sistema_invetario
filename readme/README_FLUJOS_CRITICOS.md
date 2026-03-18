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

## 3. Venta -> inventario -> caja

1. Se valida que el usuario tenga caja abierta, cliente (si aplica) y productos activos con stock.
2. Precio unitario se toma desde `productos.precio_venta`.
3. Se crea `venta` y `venta_detalles`.
4. Se descargan lotes FIFO en inventario.
5. Se crean movimientos de inventario (`salida_venta`).
6. Si es efectivo, se registra movimiento automatico en caja.

## 4. Arqueo y cierre de caja

1. Arqueo calcula diferencia entre sistema y contado, guarda evidencia.
2. Cierre toma monto contado del ultimo arqueo (si existe).
3. Cierre valida umbral de faltante (`caja_alerta_faltante_monto`).
4. Si excede umbral, no permite cerrar.
5. Si cierra, consolida totales y registra movimiento de capital correspondiente.

## 5. Devolucion/anulacion

1. Se valida politica por fecha y estado.
2. No se borra historico; se marca/anula y compensa.
3. Se registran movimientos de inventario/caja para trazabilidad.

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
