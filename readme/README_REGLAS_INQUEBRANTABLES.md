# Reglas Inquebrantables

Este documento define reglas de negocio que no deben romperse. Cualquier cambio que toque estas reglas debe justificarse y actualizar este archivo.

## 1. Seguridad y acceso

- La validacion final siempre se hace en backend.
- Permisos por rol deben respetarse en middleware y controlador.
- En historial de ventas, solo `admin` puede consultar ventas de todos los usuarios; los demas roles solo consultan su propio historial.
- El modulo de `Manual` usa una sola pantalla con contenido por rol.
- admin visualiza manual completo.
- operador/cajero visualizan manual operativo.
- Usuario inactivo o eliminado no puede iniciar sesion.
- Un permiso con `activo = 0` no se muestra en el menú de ningún rol.
- Para roles no administradores, un permiso inactivo no autoriza rutas ni APIs.
- El administrador conserva acceso directo a las rutas y APIs inactivas, aunque estas no aparezcan en su menú.

## 2. Ventas

- Metodo de pago permitido para venta nueva: `efectivo` o `credito`.
- Para registrar una venta, el usuario debe tener caja abierta.
- Precio unitario de venta: backend toma siempre `productos.precio_venta`.
- El precio no se confia desde payload del frontend.
- Cliente inactivo no puede usarse en nuevas ventas.
- Producto inactivo no puede venderse.
- Una venta a crédito debe crear y mantener una sola cuenta por cobrar asociada.
- Un abono no puede superar el saldo pendiente y requiere caja abierta.
- Solo el último abono vigente puede anularse; la misma caja que lo recibió debe continuar abierta y pertenecer al usuario que lo anula.
- Una cuenta incobrable cierra su saldo y registra una pérdida; no acepta más abonos ni devoluciones.

## 3. Compras e inventario

- Producto inactivo no puede comprarse.
- Cantidad en compras debe ser entera mayor a 0.
- Movimientos de inventario deben reflejar compra, venta, devolucion y anulaciones.
- Inventario inicial no debe registrarse como compra.
- Inventario inicial debe registrar movimiento tipo `inventario_inicial` y lote sin `compra_detalle_id`.

## 3.1 Reportes financieros

- El estado general del negocio se calcula como `efectivo_total + cuentas_por_cobrar + inventario_valorizado`.
- El resultado se compara contra `inversion_inicial`.
- `inversion_inicial` se toma del primer movimiento `ingreso_capital` en orden cronologico.
- `inversion_inicial` no debe alterarse automaticamente por compras, ventas o ajustes.
- Las pérdidas por cuentas incobrables deben descontarse de la ganancia neta del período.

## 4. Caja

- Flujo: apertura -> movimientos -> arqueo -> cierre.
- Si ya hay caja abierta del usuario, no puede abrir otra.
- Aperturas por dia dependen de `caja_aperturas_maximas_por_dia`.
- Arqueo registra historial y no bloquea por umbral.
- Cierre valida umbral `caja_alerta_faltante_monto`.
- Si existe arqueo, cierre usa monto contado del ultimo arqueo.
- Todo reembolso de devolución en efectivo requiere caja abierta y crea un egreso trazable.

## 5. Clientes y proveedores

- Cliente con ventas registradas no se elimina; solo se desactiva.
- Proveedor con compras registradas no se elimina; solo se desactiva.

## 6. Historicos y anulaciones

- No eliminar fisicamente ventas/compras/devoluciones historicas para anular.
- Anulaciones se manejan por estado y compensaciones de inventario/caja.
- Una devolución de venta a crédito reduce primero la deuda; solo el excedente sobre el saldo se reembolsa en efectivo.
- Al anular una devolución se deben revertir inventario y todos sus impactos financieros. Si el reembolso fue en caja, la caja original debe seguir abierta.

## 7. Hora del sistema

- Timezone de app por `APP_TIMEZONE` (actual: `America/Guatemala`).
- El reloj del header toma hora base desde backend.
- Frontend solo anima segundos y resincroniza periodicamente.

## 8. Estandares de interfaz y moneda

- Se deben mantener los colores oficiales ya definidos en la interfaz del sistema; no cambiarlos por modulo sin aprobacion explicita.
- Los modales de confirmacion deben ser estandares y consistentes en todo el sistema (misma estructura, misma intencion y mismo comportamiento).
- El formato monetario estandar es con 2 decimales.
- El usuario digita decimales con punto (`.`).
- Los miles se muestran con coma (`,`).
