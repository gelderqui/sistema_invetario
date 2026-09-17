# Manual de Usuario - Sistema Inventario

Este manual esta pensado para operar el negocio en orden, sin romper reglas y sin distorsionar reportes.

---

## 1. Configuracion inicial (primer arranque)

Este es el orden recomendado cuando empiezas a usar el sistema:

1. Ingresa tu capital inicial en `Capital` (`ingreso_capital`).
2. Crea categorias de producto.
3. Crea productos y define su unidad, costo y precio.
4. Carga inventario inicial (no como compra) en `Inventario -> Inventario inicial`.
5. Crea proveedores.
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
- Metodo de pago en venta nueva: solo `efectivo`.
- El precio unitario de venta lo toma backend desde el producto.
- El arqueo no bloquea; el cierre si bloquea cuando hay faltante sobre umbral.
- Si existe arqueo, el cierre usa el monto contado del ultimo arqueo.

### 2.3 Devoluciones y anulaciones

- Venta del mismo dia: anular venta.
- Venta de dias anteriores: devolucion.
- No se elimina historico fisico; se compensa con movimientos de inventario/caja.

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

---

## 4. Transacciones con capital

El modulo Capital administra fondos globales del negocio (`caja_general` y `banco`).

### 4.1 Reglas

- No se permite transferir de una cuenta hacia la misma cuenta.
- La fecha de transaccion se toma del sistema.
- Gastos pagados con `caja_general` o `banco` afectan saldos de capital.

### 4.2 Recomendacion operativa

- Mantener `caja_general` como fondo central.
- Usar `banco` para salidas/entradas bancarias reales.
- Evitar usar capital para operaciones ficticias.

---

## 5. Como leer los reportes

Ruta: `Reportes`

### 5.1 Estado general del negocio

Muestra la foto actual del negocio:

- Caja POS abierta.
- Caja general.
- Banco.
- Total efectivo.
- Inventario valorizado.
- Total negocio.
- Inversion inicial.
- Resultado vs inversion.

Formula principal:

- `total_negocio = efectivo_total + inventario_valorizado`
- `resultado = total_negocio - inversion_inicial`

### 5.2 Utilidad del periodo

Muestra:

- Ventas brutas.
- Devoluciones.
- Ventas netas.
- Costo de ventas.
- Costo revertido por devoluciones.
- Ganancia bruta.
- Gastos.
- Perdidas de inventario.
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

## 6. Configuraciones (al final de la operacion)

Ruta: `Configuraciones`

Codigos relevantes:

- `nombre_empresa`
- `tiempo_sesion`
- `caja_alerta_faltante_monto`
- `devolucion_limite_dias_cajero`
- `porcentaje_utilidad_compra`
- `caja_aperturas_maximas_por_dia`
- `inversion_inicial`

Reglas de valor:

- `nombre_empresa` acepta texto.
- Las demas aceptan enteros.
- Minimo general: `0`.
- `devolucion_limite_dias_cajero` minimo `2`.
- `caja_aperturas_maximas_por_dia` minimo `1`.

---

## 7. Checklist de uso saludable

- Cargar inventario de arranque por `Inventario inicial`, no por compras.
- Revisar reportes al cierre del dia o semana.
- Mantener categorias/productos/proveedores en estado correcto.
- Registrar gastos siempre con metodo de pago correcto.
- Cerrar caja cada jornada para mantener trazabilidad financiera.

---

## 8. Notas finales

- Este manual es de solo lectura en pantalla.
- Si cambia una regla de negocio, actualizar este archivo en la misma entrega.
