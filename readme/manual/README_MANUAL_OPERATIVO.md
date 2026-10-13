# Manual Operativo

Este manual es para usuarios operativos. Incluye solo lo necesario para trabajar el dia a dia.

## 1. Caja

1. Abrir caja al iniciar turno.
2. Registrar movimientos de caja cuando aplique.
3. Hacer arqueo una o varias veces durante el dia.
4. Cerrar caja al final del turno.

Reglas clave:

- No puedes vender si no hay caja abierta.
- El arqueo no bloquea.
- El cierre puede bloquearse por faltante alto.
- El umbral de faltante lo define Administracion en Configuraciones.

## 2. Ventas

1. Seleccionar cliente.
2. Agregar productos.
3. Verificar cantidades.
4. Aplicar descuento si corresponde.
5. Elegir efectivo o crédito y guardar.

Reglas clave:

- El precio de venta lo toma backend del precio vigente del producto.
- Ese precio vigente normalmente queda actualizado por la ultima compra aplicada.
- Ese precio no se modifica manualmente al vender.
- El descuento si puede usarse cuando aplique.

Anulaciones y devoluciones:

- Venta del mismo dia: se anula venta.
- Venta de dias anteriores: se registra devolucion.
- Una venta con devoluciones activas no puede anularse.
- En una devolución de venta al contado debes tener caja abierta, porque el sistema registra el reembolso.
- En una devolución de venta a crédito, el sistema reduce primero la deuda; solo devuelve efectivo si queda un excedente después de cubrirla.
- Al anular venta/devolución, el sistema ajusta inventario y sus efectos financieros automáticamente.

## 2.1 Cuentas por cobrar

- Una venta a crédito crea una cuenta pendiente del cliente.
- Para registrar un abono debes tener caja abierta.
- Usa **Anular** únicamente si el último abono fue equivocado; se requiere que la misma caja siga abierta.
- Usa **Incobrable** solo cuando la deuda no podrá recuperarse; debes escribir el motivo.

## 3. Compras

1. Seleccionar proveedor activo.
2. Seleccionar metodo de pago (`caja_general` o `banco`).
3. Agregar productos con cantidad, costo y precio de venta por item.
4. Confirmar compra.

Reglas clave:

- Las compras suben inventario y actualizan costos y precio vigente del producto.
- Las compras descuentan capital segun metodo de pago y la anulacion devuelve ese saldo.
- Solo proveedores activos pueden usarse.
- Solo compras activas pueden anularse.
- Si un lote de la compra ya fue consumido, la compra no puede anularse.
- Cuando se anula una compra valida, se revierte inventario y se devuelve capital a la cuenta de pago.

### 3.1 Consignación y pago a proveedor

La consignación se habilita desde Configuraciones. Al registrar una compra consignada, marca **Mercancía en consignación**. Las unidades entran al inventario, pero no se descuenta dinero en ese momento.

Cuando vendas esas unidades, se crea automáticamente una cuenta pendiente para el proveedor. Para pagarla entra a `Cuentas por pagar`, selecciona cargos de un solo proveedor y registra el pago desde Caja general o Banco. Puede ser parcial o total.

No uses Gastos para pagar la consignación. Si la venta consignada se devuelve o se anula antes del pago, el sistema revierte el cargo; si ya hay pago aplicado, no permite la reversión.

## 4. Inventario

Secciones principales:

- Stock: consulta existencias.
- Stock propio / consignado: muestra qué parte del stock físico pertenece al negocio y qué parte está en consignación.
- Movimientos: trazabilidad de entradas/salidas.
- Ajustes: correcciones de inventario.
- Inventario inicial: carga de arranque sin registrarlo como compra.

## 5. Catalogos

Se usan para mantener datos base:

- Categorias
- Productos
- Proveedores
- Unidades de medida

Recomendacion:

- Mantener estados activos/inactivos correctamente para evitar errores en compras y ventas.

Reglas de eliminacion y desactivacion:

- Unidades de medida:
- Si esta inactiva, no aparece para crear/editar productos.
- Solo se elimina si no tiene productos asociados.
- No se puede desactivar si tiene productos activos asociados.

- Productos:
- Inactivo: no se usa en compras/ventas nuevas.
- Eliminar: solo si no tiene historial de compras ni ventas.

- Categorias:
- Inactiva: no se asigna a productos nuevos (ni al cambiar categoria en edicion).
- Eliminar: solo si no tiene productos asociados.

- Proveedores:
- Inactivo: no se puede seleccionar en compras.
- Eliminar: solo si no tiene compras.
