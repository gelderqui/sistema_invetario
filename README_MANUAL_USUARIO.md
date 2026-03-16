# Manual de Usuario - Sistema Inventario

Este documento es para usuario final. Explica para que sirve cada pantalla, las acciones principales (botones) y el flujo recomendado de operacion.

## Flujo operativo recomendado (orden sugerido)

1. Crear categorias.
2. Crear productos y vincular categoria/proveedor cuando aplique.
3. Crear proveedores.
4. Registrar compras para ingresar inventario y actualizar costos.
5. Abrir caja.
6. Registrar ventas en POS.
7. Registrar movimientos/arqueos de caja durante el turno.
8. Cerrar caja al final del turno.
9. Revisar historial, devoluciones y ajustes cuando corresponda.

## 1. Estructura actual del menu

Orden principal:

1. Dashboard
2. Capital
3. Caja
4. Ventas
5. Compras
6. Inventario
7. Catalogo
8. Configuracion

Submodulos relevantes:

- Caja: Apertura, Movimientos, Arqueo, Cierre
- Capital: Resumen y transferencias entre cuentas
- Ventas: POS, Clientes, Devoluciones, Historial
- Compras: Compras y proveedores
- Inventario: Existencias, lotes, movimientos y ajustes
- Catalogo: Productos, Categorias, Proveedores, otros catalogos

## 2. Reglas generales de seguridad y consistencia

- La validacion final siempre se realiza en backend.
- Los estados (activo/inactivo/anulado) tienen efecto real, no solo visual.
- No usar eliminaciones fisicas para operaciones historicas criticas (ventas, compras, devoluciones).

## 3. Caja

- Flujo operativo permitido: apertura -> movimientos -> arqueo -> cierre.
- Arqueo registra historial (puede hacerse varias veces con caja abierta).
- Al registrar arqueo, se confirma exito en pantalla.
- El umbral `caja_alerta_faltante_monto` se evalua en cierre, no en arqueo.
- Si la diferencia de cierre excede umbral permitido, no se puede cerrar caja.
- Si existe arqueo, cierre toma automaticamente el monto contado del ultimo arqueo.
- En cierre, el campo Monto contado final queda bloqueado cuando ya existe arqueo.
- Tras cerrar exitosamente, se limpian formularios de caja y la vista vuelve a Apertura.

## 4. Capital

- No se permite transferir hacia la misma cuenta de origen.
- La fecha del movimiento se toma del sistema (fecha actual).

## 5. Ventas, devoluciones y anulaciones

Reglas operativas de POS:

- Metodo de pago permitido para venta nueva: solo `efectivo`.
- El precio unitario en venta no es editable a nivel de negocio: backend toma siempre el precio vigente del producto.
- Para ajustes comerciales se usa `descuento` (habilitado por checkbox en UI).
- En buscador de producto se permite localizar por nombre, codigo de barras o palabras clave.

### 5.1 Estados

- Venta/Compra/Devolucion usan estado: `activo` o `anulada`.
- Al anular, no se borra el registro; se compensa inventario/caja segun corresponda.

### 5.2 Regla por fecha

- Venta del mismo dia: se anula la venta.
- Venta de dias anteriores: se procesa con devolucion.

### 5.3 Compensaciones

- Anulacion de venta:
  - Inventario: movimiento `anulacion_venta` (+stock)
  - Caja (si fue en efectivo): movimiento `anulacion_venta` (egreso)
- Anulacion de compra:
  - Inventario: movimiento `anulacion_compra` (-stock), validando lotes no consumidos
- Anulacion de devolucion:
  - Inventario: movimiento `anulacion_devolucion` (-stock)

### 5.4 Tickets PDF

- Venta y devolucion generan ticket PDF tamano pequeno.
- Se puede imprimir al guardar y desde historial.

## 6. Usuarios

### 6.1 Estados y acceso

- Usuario activo: puede iniciar sesion.
- Usuario inactivo: no puede iniciar sesion.
- Usuario eliminado (borrado logico): no puede iniciar sesion y no puede reactivarse.

### 6.2 Reglas administrativas

- El usuario admin principal no se puede eliminar.
- En edicion, el username es solo lectura.
- Si un admin cambia la contrasena de un usuario, se cierran solo las sesiones de ese usuario.

### 6.3 Vista de usuarios

- Hay accion directa en tabla para activar/inactivar usuario.
- Al desactivar se muestra modal de confirmacion indicando que no podra iniciar sesion.
- Orden de listado: Activo -> Inactivo -> Eliminado.
- Filtros en listado: estado (default: Todos) y nombre.

## 7. Roles

### 7.1 Estados y acceso

- Rol activo: los usuarios con ese rol pueden iniciar sesion (si su usuario esta activo).
- Rol inactivo: usuarios con ese rol no pueden iniciar sesion.

### 7.2 Asignacion a usuarios

- No se puede crear usuario nuevo con rol inactivo.
- No se puede asignar un rol inactivo al cambiar rol de usuario.
- Si un usuario ya tenia ese rol y solo se cambia su estado activo/inactivo, la operacion es valida.

### 7.3 Reglas de mantenimiento

- El rol admin no se puede desactivar ni eliminar.
- No se puede eliminar un rol con usuarios asignados.
- En la tabla de roles hay accion directa para activar/inactivar con modal de confirmacion y advertencia.

## 8. Proveedores

### 8.1 Estados

- Proveedor activo/inactivo disponible desde la tabla.
- Hay filtros en la vista: estado (Todos, Activo, Inactivo) y nombre.
- Orden en la vista: por nombre.

### 8.2 Impacto en compras

- En Compras solo se muestran proveedores activos.
- Backend tambien bloquea registrar compra con proveedor inactivo.

### 8.3 Eliminacion

- Se puede eliminar proveedor solo si no tiene compras asociadas.
- Si tiene compras, se muestra error: no se puede eliminar, solo desactivar.

## 8.4 Clientes (reglas alineadas a proveedores)

- Cliente activo/inactivo disponible desde la tabla.
- Cliente inactivo no puede usarse en nuevas ventas.
- Cliente con ventas registradas no puede eliminarse; solo desactivarse.

## 9. Categorias

### 9.1 Estados y uso en productos

- Categoria activa/inactiva disponible desde la tabla.
- Categoria inactiva no aparece en el formulario de crear/editar producto.
- Backend tambien bloquea asignar una categoria inactiva por peticion manual.

### 9.2 Eliminacion

- Solo se puede eliminar una categoria si no tiene productos asociados.
- Si existen productos, el sistema muestra mensaje de error indicando que no puede eliminarse por esa razon.

### 9.3 Listado y filtros

- Filtros en la vista: estado (Todos, Activo, Inactivo) y nombre.
- Orden en la vista: por nombre.

## 10. Productos

### 10.1 Estados y uso operativo

- Producto activo/inactivo disponible desde la tabla.
- Producto inactivo no aparece en los catalogos de Ventas ni Compras.
- Backend bloquea registrar venta o compra con producto inactivo (aunque se manipule la peticion).

### 10.2 Eliminacion

- Solo se puede eliminar un producto si no tiene compras ni ventas registradas.
- Si ya tiene compras o ventas, el sistema muestra mensaje de error y no permite eliminar.

### 10.3 Formulario de crear/editar producto

- Categoria y Proveedor usan selector buscable (`vue-multiselect`) con ingreso de texto.
- Se permite dejar Categoria o Proveedor sin valor (opcional).

### 10.4 Listado y filtros

- Filtros en la vista: estado (Todos, Activo, Inactivo) y nombre.
- Orden en la vista: por nombre.

## 11. Configuracion

Codigos vigentes:

- `nombre_empresa` (texto)
- `tiempo_sesion` (entero en dias)
- `caja_alerta_faltante_monto` (entero)
- `devolucion_limite_dias_cajero` (entero)
- `porcentaje_utilidad_compra` (entero en porcentaje)
- `caja_aperturas_maximas_por_dia` (entero)

Reglas:

- Solo `nombre_empresa` acepta texto.
- Las demas configuraciones aceptan entero.
- Minimo entero general: 0.
- Excepcion: `devolucion_limite_dias_cajero` minimo 2.
- Excepcion: `caja_aperturas_maximas_por_dia` minimo 1.
- En Compras, el precio de venta sugerido se calcula como costo + `porcentaje_utilidad_compra`.

## 12. UX y errores

- Formato monetario estandar en todo el frontend: 2 decimales, miles con coma y decimal con punto.
- Los errores de backend se muestran al usuario con notificacion global y mensajes locales por vista.
- Inputs numericos sin flechas visuales para una captura mas limpia.
- Reloj de header: hora base tomada de backend y animacion en frontend.
- Resincronizacion de hora con backend cada 30 minutos para reducir peticiones.

## 13. Criterios para cambios futuros

Al agregar o modificar reglas, validar siempre:

1. Backend: validacion y reglas de negocio.
2. Frontend: mensajes claros, modal de confirmacion cuando aplique.
3. Listados: filtro y orden coherentes con la operacion.
4. Seguridad: evitar que una peticion manipulada salte reglas criticas.
5. Manual: actualizar este archivo en la misma entrega.

## 14. Checklist rapido de regresion

- Usuario inactivo no inicia sesion.
- Usuario con rol inactivo no inicia sesion.
- No se puede asignar rol inactivo a un usuario nuevo o al cambiar rol.
- En roles, activar/inactivar muestra confirmacion y mensaje.
- En usuarios, activar/inactivar muestra confirmacion y mensaje.
- En usuarios, filtros por estado y nombre funcionan sobre listados grandes.
- Proveedor inactivo no aparece en compras.
- No se puede registrar compra con proveedor inactivo.
- En compras, la cantidad solo acepta enteros.
- En compras, el precio de venta se sugiere automaticamente segun costo + porcentaje configurable, aunque puede editarse por item.
- Proveedor con compras no se elimina; solo se desactiva.
- Categoria inactiva no aparece al crear/editar producto.
- No se puede asignar categoria inactiva por backend.
- Categoria con productos asociados no se puede eliminar.
- Producto inactivo no aparece en ventas ni compras.
- No se puede registrar venta/compra con producto inactivo por backend.
- Producto con compras o ventas previas no se puede eliminar.
- En proveedores, categorias y productos existen filtros por estado y nombre.
- En caja, el cierre usa monto del ultimo arqueo cuando exista.
- En caja, si falta excede el umbral configurado, cierre queda bloqueado.
- En caja, aperturas diarias respetan `caja_aperturas_maximas_por_dia`.
- En ventas, backend ignora precio enviado y usa `precio_venta` del producto.
- En clientes, no se permite eliminar si existen ventas asociadas.

---

Sugerencia de mantenimiento:

- Mantener este manual sincronizado con cada cambio funcional para evitar perdida de reglas acordadas.
