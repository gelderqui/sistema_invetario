# Manual de Usuario - Sistema Inventario

Este manual esta pensado para operar el negocio en orden, sin romper reglas y sin distorsionar reportes.

---

## 1. Configuracion inicial (primer arranque)

Este es el orden recomendado cuando empiezas a usar el sistema:

1. Ingresa tu capital inicial en `Capital` (`ingreso_capital`).
2. Crea categorias de producto.
3. Crea productos y define su unidad, costo, precio normal y, cuando aplique, precio mayorista.
4. Carga inventario inicial (no como compra) en `Inventario -> Inventario inicial`.
5. Crea proveedores; registra su NIT cuando lo conozcas para poder ubicarlo por nombre o NIT después.
6. Si aplica, registra clientes.

### 1.1 Regla clave de arranque

- **Inventario inicial NO se registra como compra**.
- Se usa el modulo `Inventario inicial` para:
- subir stock de arranque.
- crear lotes.
- generar trazabilidad (`inventario_inicial`).
- no alterar reportes de compras reales.

---

## 2. Proceso diario de caja y ventas

### 2.1 Flujo recomendado

1. Abrir caja.
2. Registrar ventas en POS.
3. Registrar movimientos de caja cuando corresponda.
4. Realizar arqueo (puedes hacer varios durante el turno).
5. Cerrar caja al final del turno.

### 2.2 Reglas importantes

- Para vender, el usuario debe tener caja abierta.
- Metodo de pago en venta nueva: `efectivo` o `credito`.
- El precio unitario de venta lo toma backend desde el producto: usa el precio normal para clientes minoristas y el precio mayorista cuando el cliente está clasificado como mayorista. El precio mayorista es opcional: si queda vacío, en `0` o sin definir, usa su precio minorista. Los precios negativos no se permiten.
- En **Nueva venta**, la etiqueta **Cliente** cambia a **Cliente mayorista** al seleccionar un cliente de ese tipo. Al seleccionar un minorista o quitar la selección, vuelve a **Cliente**.
- El arqueo no bloquea; el cierre si bloquea cuando hay faltante sobre umbral.
- Si existe arqueo, el cierre usa el monto contado del ultimo arqueo.

### 2.3 Devoluciones y anulaciones

- Venta del mismo dia: anular venta.
- Venta de dias anteriores: devolucion.
- Cualquier usuario con permiso de Devoluciones puede procesar una devolución.
- La fecha se asigna automáticamente al día actual.
- Una venta en efectivo se reembolsa desde caja POS si la caja original aún está abierta; cuando esa caja ya cerró, el dinero sale de Caja general. Una caja nueva no cambia el cierre anterior.
- Si la venta tuvo descuento, la devolución usa el precio neto correspondiente a los productos devueltos. Por ejemplo, si tres productos sumaban Q60 y pagó Q45 después del descuento, devolver los tres reintegra Q45, no Q60.
- Puede devolver por partes: el sistema conserva el importe pendiente y ajusta los centavos al completar las unidades. Una devolución anulada vuelve a dejar disponibles sus unidades e importe. El precio actual del catálogo no cambia lo que corresponde devolver de una venta anterior.
- En una venta a crédito, el sistema baja primero la deuda. Si lo devuelto supera el saldo, reembolsa el excedente desde caja; si sus abonos ya pasaron por una caja cerrada, usa Caja general.
- Al anular una devolución, el sistema revierte inventario y el efecto en caja o crédito. Si la caja del reembolso ya cerró, reintegra el dinero a Caja general sin cambiar el cierre histórico.
- Una venta del mismo día se puede anular aunque su caja ya haya cerrado: el reembolso sale de Caja general. Si no hay saldo suficiente o la cuenta no está activa, se muestra un error y la venta sigue vigente, sin reponer inventario.
- No se elimina histórico físico; se compensa con movimientos trazables.

### 2.4 Ventas a crédito y cuentas por cobrar

Al guardar una venta a crédito debes seleccionar un cliente. El sistema crea la cuenta por cobrar por el total de la venta.

- Un abono se recibe en efectivo y requiere que quien lo registre tenga caja abierta.
- El abono disminuye el saldo de la cuenta y aumenta la caja.
- En el detalle de la cuenta, el último abono vigente puede anularse si la misma caja sigue abierta y pertenece al usuario que lo recibió.
- La anulación del abono vuelve a aumentar la deuda y saca el mismo monto de caja.
- Reportes muestra por separado los abonos recibidos, sus anulaciones y el cobro neto dentro del período seleccionado. Si abono y anulación están en fechas distintas, cada uno aparece en el período que le corresponde.
- Si una deuda ya no se recuperará, se puede marcar como **incobrable** indicando el motivo. Esto cierra su saldo y se refleja como pérdida en Reportes.

### 2.5 Consulta de precios

Ruta: `Consulta de precios`.

Use esta pantalla cuando un cliente solo necesita confirmar un precio. Es independiente del POS, por lo que no requiere abrir caja ni crea una venta.

1. Escriba al menos tres caracteres o escanee el código de barra; también puede buscar por nombre o palabras clave.
2. La búsqueda se ejecuta automáticamente después de una pausa breve. Puede presionar **Buscar** o Enter si desea buscar antes.
3. Revise el precio normal, la unidad y la existencia mostrados para cada producto encontrado.

La consulta no muestra costos ni permite modificar productos. Si desea vender, abra el POS y siga el flujo normal de caja.

---

## 3. Proceso de compras

### 3.1 Flujo recomendado

1. Seleccionar proveedor activo.
2. Agregar productos con cantidades enteras y costos.
3. Confirmar compra.

### 3.2 Que hace el sistema al guardar

- Crea compra y detalles.
- Aumenta inventario.
- Crea lotes en inventario.
- Registra movimientos de inventario.
- Recalcula costos y precio sugerido segun configuracion.

### 3.3 Reglas importantes

- Proveedor inactivo no se usa en compras.
- Producto inactivo no se puede comprar.
- Cantidad en compra debe ser mayor a 0.
- La columna **Precio sugerido** se calcula desde el costo y el porcentaje configurado. Debajo se muestra el precio actual del producto para compararlo antes de guardar.
- Una compra actualiza el precio normal aplicado; el precio mayorista se administra desde el catálogo de Productos.
- En `Catálogo -> Proveedores`, el NIT es opcional. El buscador de proveedores encuentra registros por nombre o por NIT.

### 3.3.1 Pago inmediato desde caja POS

Si recibes mercancía durante tu turno y la pagas con el efectivo de tu caja POS:

1. Debes tener acceso a Compras y Ventas, además de tu caja POS abierta.
2. En **Método de pago** selecciona **Efectivo de mi caja POS abierta**.
3. Guarda la compra normalmente.

El sistema registra la compra e inventario, y descuenta su total de tu caja POS automáticamente. No registres un movimiento manual adicional de caja.

La caja debe tener efectivo suficiente para cubrir toda la compra. Si el saldo no alcanza, el sistema muestra el disponible y no registra parcialmente la compra ni el inventario.

Si anulas la compra mientras la caja sigue abierta, el dinero vuelve a esa misma caja. Si la caja ya fue cerrada, la anulación sigue permitida: el dinero se reintegra a **Caja general** y no cambia el arqueo ni los movimientos históricos de la caja cerrada.

### 3.4 Documento de compra y factura a Mi NIT

En toda compra seleccione el documento que le entregó el proveedor:

- **Sin documento**: no se registra comprobante.
- **Recibo**: ingrese el número del recibo.
- **Factura**: ingrese número, serie/DTE y defina si fue emitida como **CF** o a **Mi NIT**.

Una factura a **Mi NIT** requiere que el NIT del negocio esté configurado en `Configuraciones`, además del NIT del proveedor. El sistema marca las unidades recibidas como **inventario con factura**. Una factura CF y los demás documentos se consideran inventario sin factura fiscal.

Esta elección está disponible para pago inmediato, compra a crédito y consignación. El documento no cambia la forma de pago: una compra a crédito continúa pendiente de pago y una consignación continúa liquidándose según lo vendido.

En la tabla de Compras puede filtrar por proveedor, documento y receptor de factura. Los botones **Exportar PDF** y **Exportar Excel** generan exactamente el listado filtrado. En `Inventario` se puede aplicar el mismo filtro para identificar productos con existencias provenientes de cada tipo de documento y exportar el resultado.

### 3.5 Mercancía en consignación

Use consignación solo cuando el proveedor entrega la mercancía y el negocio debe pagarla después de venderla.

1. Un administrador habilita `habilitar_consignacion` con valor `1` en `Configuraciones`.
2. Ve a `Compras` y selecciona **Consignación** como tipo de compra.
3. Selecciona proveedor, productos, cantidades, costo y precio de venta; no se solicita método de pago.
4. Guarda la recepción.

Al guardar, las unidades entran al inventario y pueden venderse normalmente, pero no se descuenta Caja general ni Banco. La compra queda identificada como consignación.

Cuando se vende una unidad consignada, el sistema calcula automáticamente lo que se debe al proveedor según el costo de su lote.

### 3.6 Compra a crédito

Use **Compra a crédito** cuando la mercancía pasa a ser del negocio al recibirla, pero el proveedor se cobrará después.

1. Ve a `Compras` y selecciona **Compra a crédito**.
2. Registra proveedor, documento y productos normalmente.
3. Guarda la compra: el inventario entra como propio y no se descuenta Caja general ni Banco.
4. El total se crea automáticamente como una cuenta pendiente del proveedor.

Después, registra un abono o pago total desde `Cuentas por pagar`, seleccionando Caja general o Banco.

### 3.7 Cuentas por pagar a proveedores

Ruta: `Cuentas por pagar`.

1. Selecciona uno o más cargos del mismo proveedor.
2. Presiona **Registrar pago**.
3. Indica la fecha, Caja general o Banco y el monto para cada cargo.
4. Guarda el pago.

Puedes pagar un cargo de forma parcial. El sistema muestra el saldo restante y al completarlo lo marca como pagado. No registres este pago como gasto, porque el costo ya se usa para calcular la utilidad de la venta.

Si una venta consignada se anula o se devuelve antes de pagar al proveedor, el sistema reduce el cargo automáticamente. Si anulas la devolución, el cargo vuelve a su cantidad y saldo anteriores. Cuando se aplicó un pago al cargo después de devolver, la anulación se bloquea completa para no descoordinar inventario, efectivo y deuda.

---

## 3.8 Clientes mayoristas

En `Catálogo -> Clientes`, seleccione el tipo **Minorista** o **Mayorista**. Todos los clientes existentes y nuevos inician como minoristas por defecto. El tipo define el precio aplicado automáticamente en el POS; no depende del NIT ni del nombre del cliente.

---

## 4. Transacciones con capital

El modulo Capital administra fondos globales del negocio (`caja_general` y `banco`).

### 4.1 Reglas

- No se permite transferir de una cuenta hacia la misma cuenta.
- La fecha de transaccion se toma del sistema.
- Gastos pagados con `caja_general` o `banco` afectan saldos de capital.
- Los pagos de consignación desde `Cuentas por pagar` también reducen Caja general o Banco.

### 4.2 Recomendacion operativa

- Mantener `caja_general` como fondo central.
- Usar `banco` para salidas/entradas bancarias reales.
- Evitar usar capital para operaciones ficticias.

---

## 5. Agente BI

Este modulo es independiente de la Caja POS y permite cuadrar fondos de BI.

1. Registra las cuentas de tipo `Cuentas por pagar` o `Cuentas por cobrar`.
2. Registra abonos o ajustes para conservar el saldo de cada cuenta.
3. Haz el arqueo de caja chica con las denominaciones reales.
4. Guarda un cuadre ingresando Capital, Banco y Caja. El sistema toma caja chica del arqueo y calcula las cuentas y la diferencia automaticamente.

La diferencia queda registrada en el historial de cuadres y no se corrige automaticamente.

---

## 6. Activos

Ruta: `Activos`

Registra bienes de uso diario que no forman parte del inventario de venta, como estantes, refrigeradoras y equipo. Puedes crearlos, editarlos y desactivarlos sin borrar su registro.

---

## 7. Como leer los reportes

Ruta: `Reportes`

### 5.1 Estado general del negocio

Muestra la foto actual del negocio:

- Caja POS abierta.
- Caja general.
- Banco.
- Total efectivo.
- Cuentas por cobrar.
- Cuentas por pagar a proveedores.
- Inventario valorizado.
- Mercancía en consignación en custodia.
- Total negocio.
- Inversion inicial.
- Resultado vs inversion.

Formula principal:

- `total_negocio = efectivo_total + cuentas_por_cobrar + inventario_valorizado - cuentas_por_pagar_proveedores`
- `resultado = total_negocio - inversion_inicial`

### 5.2 Utilidad del periodo

Muestra:

- Ventas brutas.
- Devoluciones.
- Ventas netas.
- Costo de ventas.
- Costo revertido por devoluciones.
- Los costos corresponden únicamente a ventas y devoluciones activas. Una operación anulada conserva su historial, pero su ingreso y costo dejan de modificar la utilidad tanto en Reportes como en Dashboard.
- Ganancia bruta.
- Gastos.
- Perdidas de inventario.
- Pérdidas por cuentas incobrables.
- Ganancia neta.

### 5.3 Flujo de caja

Consolidado de `movimientos_caja` por periodo:

- ingresos.
- egresos.
- flujo neto.
- detalle por tipo de movimiento.

### 5.4 Inventario valorizado

Muestra valor de productos en existencia:

- total valorizado.
- valor por categoria.
- top productos por valor.

---

## 7. Configuraciones (al final de la operacion)

Ruta: `Configuraciones`

Codigos relevantes:

- `nombre_empresa`
- `tiempo_sesion`
- `caja_alerta_faltante_monto`
- `devolucion_limite_dias_cajero`
- `porcentaje_utilidad_compra`
- `caja_aperturas_maximas_por_dia`
- `habilitar_consignacion` (`0` la oculta; `1` permite usarla)
- `nit_empresa` (NIT del negocio para identificar facturas emitidas a su nombre)
- `inversion_inicial`

Reglas de valor:

- `nombre_empresa` acepta texto y es obligatorio.
- `nit_empresa` es opcional, acepta únicamente números y puede dejarse vacío. Al vaciarlo, las compras anteriores conservan el NIT con el que fueron registradas, pero no podrás usar **Factura a Mi NIT** en compras nuevas hasta configurarlo otra vez.
- Las demas aceptan enteros.
- Minimo general: `0`.
- `devolucion_limite_dias_cajero` minimo `2`.
- `caja_aperturas_maximas_por_dia` minimo `1`.

---

## 8. Checklist de uso saludable

- Cargar inventario de arranque por `Inventario inicial`, no por compras.
- Revisar reportes al cierre del dia o semana.
- Mantener categorias/productos/proveedores en estado correcto.
- Registrar gastos siempre con metodo de pago correcto.
- Cerrar caja cada jornada para mantener trazabilidad financiera.

---

## 9. Notas finales

- Este manual es de solo lectura en pantalla.
- Si un administrador desactiva tu usuario o tu rol mientras tienes una sesión abierta, la siguiente operación se bloquea, la sesión se cierra y debes contactar al administrador. Ningún cambio solicitado en esa petición llega a guardarse.
- Si cambia una regla de negocio, actualizar este archivo en la misma entrega.
