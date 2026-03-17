# Reglas Inquebrantables

Este documento define reglas de negocio que no deben romperse. Cualquier cambio que toque estas reglas debe justificarse y actualizar este archivo.

## 1. Seguridad y acceso

- La validacion final siempre se hace en backend.
- Permisos por rol deben respetarse en middleware y controlador.
- Usuario inactivo o eliminado no puede iniciar sesion.

## 2. Ventas

- Metodo de pago permitido para venta nueva: solo `efectivo`.
- Para registrar una venta, el usuario debe tener caja abierta.
- Precio unitario de venta: backend toma siempre `productos.precio_venta`.
- El precio no se confia desde payload del frontend.
- Cliente inactivo no puede usarse en nuevas ventas.
- Producto inactivo no puede venderse.

## 3. Compras e inventario

- Producto inactivo no puede comprarse.
- Cantidad en compras debe ser entera mayor a 0.
- Movimientos de inventario deben reflejar compra, venta, devolucion y anulaciones.

## 4. Caja

- Flujo: apertura -> movimientos -> arqueo -> cierre.
- Si ya hay caja abierta del usuario, no puede abrir otra.
- Aperturas por dia dependen de `caja_aperturas_maximas_por_dia`.
- Arqueo registra historial y no bloquea por umbral.
- Cierre valida umbral `caja_alerta_faltante_monto`.
- Si existe arqueo, cierre usa monto contado del ultimo arqueo.

## 5. Clientes y proveedores

- Cliente con ventas registradas no se elimina; solo se desactiva.
- Proveedor con compras registradas no se elimina; solo se desactiva.

## 6. Historicos y anulaciones

- No eliminar fisicamente ventas/compras/devoluciones historicas para anular.
- Anulaciones se manejan por estado y compensaciones de inventario/caja.

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
