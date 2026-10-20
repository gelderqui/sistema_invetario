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
- Toda petición autenticada debe volver a comprobar que el usuario y su rol continúan activos. Si alguno fue desactivado después del inicio de sesión, la petición se rechaza con HTTP 401 y la sesión se invalida antes de ejecutar cualquier operación.
- Un permiso con `activo = 0` no se muestra en el menú de ningún rol.
- Para roles no administradores, un permiso inactivo no autoriza rutas ni APIs.
- El administrador conserva acceso directo a las rutas y APIs, incluso si el permiso está inactivo o no está asignado al rol. El menú, en cambio, solo muestra permisos activos que estén asignados al rol administrador.

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
- El catálogo de Productos nunca modifica `stock_actual`: un producto nuevo inicia en cero y toda variación pasa por Inventario inicial, Compras, Ventas, Devoluciones o Ajustes para conservar lotes y kardex.
- Inventario inicial no debe registrarse como compra.
- Inventario inicial debe registrar movimiento tipo `inventario_inicial` y lote sin `compra_detalle_id`.
- Una compra consignada aumenta el stock, pero no descuenta `caja_general` ni `banco`.
- Una compra de contado pagada con caja POS requiere acceso a Compras y Ventas, más una caja propia abierta. Debe crear un movimiento negativo `compra` en esa caja y no una salida directa de capital.
- Una compra desde caja POS nunca puede dejar saldo negativo. La disponibilidad se valida con bloqueo dentro de la transacción antes de crear compra, inventario o egreso.
- La anulación de una compra pagada con caja POS abierta debe crear el movimiento positivo compensatorio en la misma caja. Si la caja está cerrada, no debe cambiar su histórico: el reintegro debe entrar a Caja general mediante capital.
- Al anular una compra se recalculan `costo_promedio` y `costo_ultimo` con las existencias y operaciones que siguen vigentes. No se modifican `precio_venta`, `precio_mayorista` ni `precio_venta_promedio`, porque pueden contener decisiones comerciales o cambios manuales posteriores.
- Una venta que consume un lote consignado debe crear un cargo al proveedor por el costo de las unidades consumidas.
- Un pago de consignación solo puede aplicarse a cargos pendientes del proveedor seleccionado y no puede superar su saldo.

## 3.1 Reportes financieros

- El estado general del negocio se calcula como `efectivo_total + cuentas_por_cobrar + inventario_valorizado - cuentas_por_pagar_proveedores`.
- El resultado se compara contra `inversion_inicial`.
- `inversion_inicial` se toma del primer movimiento `ingreso_capital` en orden cronologico.
- `inversion_inicial` no debe alterarse automaticamente por compras, ventas o ajustes.
- Las pérdidas por cuentas incobrables deben descontarse de la ganancia neta del período.
- Los cobros de crédito del período son el neto entre abonos recibidos y anulaciones ocurridas dentro del mismo rango; ambos importes también deben mostrarse por separado.

## 4. Caja

- Flujo: apertura -> movimientos -> arqueo -> cierre.
- Si ya hay caja abierta del usuario, no puede abrir otra.
- Aperturas por dia dependen de `caja_aperturas_maximas_por_dia`.
- Arqueo registra historial y no bloquea por umbral.
- Cierre valida umbral `caja_alerta_faltante_monto`.
- Si existe arqueo, cierre usa monto contado del ultimo arqueo.
- Todo reembolso de devolución en efectivo crea un egreso trazable: usa la caja POS original si sigue abierta o Caja general si ya cerró.

## 5. Clientes y proveedores

- Cliente con ventas registradas no se elimina; solo se desactiva.
- Proveedor con compras registradas no se elimina; solo se desactiva.
- El NIT del proveedor es opcional, se normaliza a números y no sustituye el NIT guardado en cada documento fiscal de compra.
- El NIT de la empresa también es opcional y puede volver a dejarse vacío. Vaciarlo impide seleccionar **Mi NIT** en compras nuevas, pero nunca modifica el receptor ni el NIT histórico almacenado en compras anteriores.

## 6. Historicos y anulaciones

- No eliminar fisicamente ventas/compras/devoluciones historicas para anular.
- Anulaciones se manejan por estado y compensaciones de inventario/caja.
- Una devolución de venta a crédito reduce primero la deuda; solo el excedente sobre el saldo se reembolsa en efectivo.
- Las devoluciones deben respetar el descuento y los precios originales de la venta. El conjunto de devoluciones activas no puede superar el importe neto vendido, en efectivo ni a crédito.
- El descuento se reparte proporcionalmente entre detalles y se calcula en centavos exactos. Cada devolución parcial utiliza el importe y la cantidad restantes; una devolución anulada deja de consumir ese saldo. Los ajustes de centavos se completan al devolver las unidades restantes.
- Al anular una venta en efectivo del mismo día, el reembolso debe quedar registrado antes de confirmar: en su caja POS original abierta o como salida de Caja general si esa caja cerró. Nunca se modifica el cierre histórico ni se confirma solo la reposición del inventario.
- Una devolución de venta con caja original cerrada reembolsa desde Caja general; en crédito primero se reduce la deuda y solo el excedente pagado usa dinero. Si falta Caja general activa o saldo, se revierte toda la operación.
- Al anular una devolución se deben revertir inventario y todos sus impactos financieros. Si el reembolso fue en Caja general se reintegra allí; si fue en caja POS y esta ya cerró, el reintegro va a Caja general sin alterar el cierre.
- Si una devolución redujo un cargo de consignación, anularla debe restituir su cantidad, monto, saldo y estado. Cualquier pago aplicado al cargo después de la devolución bloquea toda la reversión.
- Reportes y Dashboard solo consideran costos de ventas y devoluciones activas. Anular una operación conserva su kardex, pero elimina conjuntamente su ingreso y su costo del cálculo de utilidad.

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
- Un único punto siempre se interpreta como separador decimal; `1.255` se redondea a `1.26`, no se convierte en `1,255.00`.
- Texto monetario inválido nunca se convierte silenciosamente en cero. Los campos opcionales pueden aceptar vacío o `null`, pero cualquier otro formato inválido debe producir un error de validación.
