# README Context - Sistema Inventario

Este documento es el contexto principal para IA: resume reglas operativas y decisiones funcionales para entender el sistema antes de modificar codigo. La arquitectura se documenta por separado en `README_ARQUITECTURA.md`.

## 1) Objetivo del proyecto

SPA de inventario + POS sobre Laravel + Vue, con control de caja, devoluciones, ajustes y capital.

Estado funcional actual:

- Autenticacion SPA con Sanctum (cookie + sesion)
- RBAC propio por rol/permisos
- Modulos activos y orden de menu:
  - Dashboard
  - Agente BI
  - Caja Rural
  - Consulta de precios
  - Caja
  - Ventas
  - Compras
  - Inventario
  - Catalogos
  - Administracion (Capital, Activos y Reportes)
  - Configuraciones
  - Manual (solo admin)

## 2) Reglas funcionales vigentes

### Caja

- Flujo operativo: apertura, movimientos, arqueo, cierre
- El arqueo permite multiples registros durante la caja abierta (historial de cortes).
- El arqueo no bloquea por umbral; solo registra y confirma exito.
- El umbral de faltante (`caja_alerta_faltante_monto`) se aplica en cierre.
- Si el faltante supera umbral en cierre, el sistema bloquea el cierre (422).
- En cierre, si existe arqueo, el monto contado final se toma forzosamente del ultimo arqueo.
- En frontend, monto contado final queda bloqueado cuando ya existe arqueo.
- La apertura diaria por usuario es configurable por `caja_aperturas_maximas_por_dia`.

### Capital

- Transferencias entre cuentas: no se permite seleccionar la misma cuenta en origen y destino.
- En formulario de capital no se ingresa fecha manual; se usa fecha actual del sistema.
- El primer movimiento de `ingreso_capital` se considera capital inicial del negocio.
- Los ingresos de capital posteriores se consideran inyecciones de capital.

### Agente BI

- Modulo independiente de Capital y Caja POS para cuadrar fondos de BI.
- Cada cuadre guarda una fotografia historica: `capital + cuenta_por_pagar = banco + caja + caja_chica + cuenta_por_cobrar + diferencia`.
- Banco, caja y capital se capturan manualmente en el cuadre; caja chica se toma del arqueo seleccionado.
- `cuenta_por_pagar` y `cuenta_por_cobrar` comparten una tabla de cuentas diferenciada por tipo y sus saldos se calculan en backend al guardar el cuadre.
- El arqueo de caja chica es independiente de `arqueos_caja` de la tienda y conserva el detalle por denominacion.

### Activos

- Modulo independiente del inventario para bienes de uso diario como refrigeradoras, estantes y equipo.
- Los activos se desactivan logicamente; no se eliminan de la base de datos.

### Ventas, devoluciones y anulaciones

- No se eliminan ventas/compras/devoluciones fisicamente para anular.
- Se usa `estado` (`activo` o `anulada`) y se registra compensacion en inventario/caja.
- En ventas nuevas, el usuario debe tener caja abierta para poder registrar la venta.
- En ventas nuevas, el metodo de pago puede ser `efectivo` o `credito`.
- Una venta a crédito crea una cuenta por cobrar con saldo original y movimientos auditables.
- Los abonos requieren una caja abierta y generan un ingreso `abono_venta_credito` en esa caja.
- Un abono solo se puede anular si es el último abono vigente y la misma caja del abono sigue abierta para el usuario que lo recibió; la anulación restituye la deuda y crea la salida de caja compensatoria.
- Una cuenta activa con saldo puede marcarse como `incobrable`; se cierra el saldo y su pérdida se descuenta de la utilidad del período.
- En ventas nuevas, el precio unitario se toma siempre del producto en backend (no se acepta override por request). Para clientes mayoristas usa `productos.precio_mayorista` solo si es mayor que cero; vacío, `null` o `0` usan `productos.precio_venta`. Al crear o editar un producto, esos valores opcionales se guardan como `null`; omitir el campo al editar conserva el precio mayorista existente.
- En el modal Nueva venta, la etiqueta del selector muestra `Cliente mayorista` solo si el cliente seleccionado tiene `tipo_cliente = mayorista`; en los demás casos muestra `Cliente`.
- El descuento en UI se habilita manualmente por checkbox.
- En historial de ventas, `admin` puede ver todas las ventas y filtrar por usuario; los demas roles solo ven sus propios registros.
- Cuando una venta consume un lote consignado, crea un cargo al proveedor por el costo de las unidades consumidas.
- Una devolución reduce cantidad, monto y saldo del cargo de consignación solamente si no tiene pagos aplicados. Al anular esa devolución, la cadena `devolución -> movimiento de inventario -> salida original -> cargo` permite restituir los tres valores y su estado. Si después de devolver se aplicó un pago al cargo, la anulación se bloquea completa.
- Las devoluciones usan el total neto original de la venta, incluido su descuento. `ImporteDevolucionService` reparte ese total proporcionalmente entre los detalles en centavos exactos; cada parcial se calcula sobre el dinero y las unidades pendientes de ese detalle. Solo cuentan devoluciones activas, por lo que anular una libera nuevamente cantidad e importe. La suma activa nunca puede superar el total neto de la venta.
- En `detalle_devoluciones`, `precio` conserva el precio unitario original vendido y `subtotal` registra el importe neto efectivamente devuelto. El saldo de centavos se completa al devolver las unidades restantes. Las devoluciones históricas excesivas no se corrigen automáticamente: se bloquean nuevos reembolsos y se pide revisar su historial.

### Consulta de precios

- `Consulta de precios` es una opción principal, independiente de Caja y POS; permite atender una consulta sin abrir caja ni registrar una venta.
- Busca únicamente productos activos por nombre, código de barra o palabras clave y muestra precio normal, unidad y existencia actual.
- Es estrictamente de lectura: no expone costos, no modifica inventario y no permite agregar productos a una venta.
- Su permiso es `consulta_precios`; puede asignarse por sí solo a personal que ordena mercadería o atiende consultas.

### Compras, crédito de proveedor y consignación

- En Compras, `tipo_compra` define `contado`, `credito_proveedor` o `consignacion`; por defecto es contado.
- Una compra normal descuenta Caja general o Banco según su método de pago.
- Una compra de contado también puede pagarse con la caja POS abierta del mismo usuario. Para usarla, el usuario debe tener acceso a Compras y Ventas, y una caja propia abierta. La compra conserva `caja_id` y crea un movimiento negativo `compra` en esa caja, sin descontar Caja general directamente.
- Antes de registrar una compra desde caja POS, backend bloquea la caja y sus movimientos y comprueba que el saldo cubra el total. Dos compras concurrentes no pueden consumir el mismo efectivo y un rechazo no crea registros parciales.
- Al anular una compra pagada desde caja POS abierta, el inventario se revierte y se crea el movimiento positivo `anulacion_compra` en la misma caja. Si esa caja ya fue cerrada, no se altera su histórico: el reintegro entra a Caja general mediante un movimiento de capital `anulacion_compra`.
- Una compra a crédito entra como inventario propio, no descuenta capital al recibirla y crea una cuenta por pagar por el total de la compra.
- La consignación es opcional y se habilita con `habilitar_consignacion = 1`.
- Una compra consignada entra al inventario y crea lotes FIFO, pero no genera salida de capital al recibirla.
- Inventario muestra el stock físico separado en propio y consignado.
- La ruta de operación es `Cuentas por pagar`; allí se aplican pagos parciales o totales a cargos pendientes de un solo proveedor, ya sean de crédito o consignación.
- El pago a proveedor crea un movimiento de capital `pago_proveedor` desde Caja general o Banco.
- Una factura puede emitirse como `CF` o al `nit_empresa` configurado en compras de contado, crédito de proveedor y consignación. Solo la segunda clasifica sus lotes como `con_factura`; CF se clasifica como `sin_factura`.
- En ventas, los lotes `sin_factura` tienen prioridad antes de los lotes `con_factura`, conservando FIFO dentro de cada grupo.
- Compras filtra por proveedor, tipo de documento y receptor de factura; Inventario filtra existencias por el documento de la compra que originó el lote. Ambos módulos exportan a PDF o Excel exactamente los resultados de los filtros aplicados.
- `proveedores.nit` es opcional e identifica al proveedor en su catálogo; se normaliza a números y se puede buscar junto con el nombre. En una compra con factura se precarga y protege el NIT ya registrado. Si el proveedor todavía no tiene NIT, el formulario permite ingresarlo y lo actualiza dentro de la misma transacción de la compra. No reemplaza el `nit_proveedor` almacenado como evidencia histórica de cada factura específica.

### Clientes

- Cliente inactivo no puede usarse en nuevas ventas.
- Cliente con ventas registradas no puede eliminarse; solo puede desactivarse.
- `clientes.tipo_cliente` es `minorista` por defecto y puede ser `mayorista`; la clasificación define el precio aplicado en POS, no el NIT ni el nombre.

Regla de fecha:

- Venta del mismo dia: se maneja con anulacion de venta.
- Venta de dias anteriores: se maneja con devolucion.

Compensaciones:

- Anulacion de venta:
  - Inventario: movimiento `anulacion_venta` (+stock)
  - Si fue en efectivo y la caja original sigue abierta: movimiento `anulacion_venta` (egreso) en esa caja.
  - Si la caja original cerró: egreso `anulacion_venta` de Caja general; el cierre y los movimientos de la caja cerrada no se alteran. Solo se anulan ventas activas del mismo día y la operación se revierte entera si no puede pagarse el reembolso.
- Anulacion de compra:
  - Inventario: movimiento `anulacion_compra` (-stock), con validacion de lotes no consumidos
- Anulacion de devolucion:
  - Inventario: movimiento `anulacion_devolucion` (-stock)
  - Crédito: restituye la deuda que la devolución había reducido.
  - Si el reembolso salió de caja POS aún abierta, crea el ingreso compensatorio allí; si esa caja cerró o el reembolso salió de Caja general, registra el ingreso en Caja general.

Devoluciones financieras:

- Cualquier usuario con permiso `devoluciones` puede procesar devoluciones de ventas anteriores.
- La fecha la asigna el servidor al día actual; una venta del mismo día se anula, no se devuelve.
- Venta en efectivo: si la caja de la venta sigue abierta, la devolución crea un egreso `devolucion_venta` desde la caja abierta del usuario; si ya cerró, lo registra en Caja general, aun cuando exista una caja nueva abierta.
- Venta a crédito: primero reduce el saldo pendiente; solo si el total devuelto supera la deuda vigente, el excedente se reembolsa en efectivo.
- Si los abonos vigentes de una venta a crédito provienen de alguna caja cerrada, el excedente en efectivo sale de Caja general. Sin abonos, una devolución que solo disminuye deuda no mueve efectivo.
- No se permiten devoluciones de cuentas anuladas o incobrables.
- La devolución conserva los enlaces a sus movimientos de caja POS, capital y crédito; al anularla se revierten todos los efectos. `devoluciones.movimiento_capital_id` y `movimiento_capital_reversion_id` trazan los movimientos en Caja general.

### Recibos PDF

- Venta y devolucion generan ticket PDF (tamano pequeno) con opcion de imprimir.
- Disponible al guardar y desde historial.
- DomPDF resuelve ruta publica con fallback automatico para entornos mixtos:
  - `base_path/public`
  - `base_path/public_html`
  - `dirname(base_path)/public_html`
  - `$_SERVER['DOCUMENT_ROOT']`
- Si una ruta no existe, intenta la siguiente hasta encontrar una valida.
- Esto evita errores `Cannot resolve public path` en hosting compartido (ejemplo: Hostinger) sin romper entorno local.

### Reportes

- Existe un modulo `Reportes` con cinco bloques: estado general, ventas a crédito y cobros, utilidad, flujo de caja e inventario valorizado.
- Los cobros de ventas a crédito se presentan como abonos recibidos, anulaciones y cobro neto según la fecha propia de cada movimiento; una anulación no permanece contabilizada como efectivo cobrado.
- Estado general del negocio:
  - Caja POS abierta: suma de movimientos de cajas en estado abierta
  - Caja general y banco: saldos actuales de cuentas de capital
  - Inversion inicial: primer movimiento `ingreso_capital` (por fecha/id)
  - Total negocio: `efectivo_total + cuentas_por_cobrar + inventario_valorizado - cuentas_por_pagar_proveedores`
  - Resultado: `total_negocio - inversion_inicial`
- El inventario valorizado representa únicamente mercancía propia. Los lotes con `es_consignacion = 1` se muestran aparte como mercancía en custodia y no aumentan el patrimonio. Para conservar inventario histórico sin lote, se considera propio el remanente positivo entre `productos.stock_actual` y el total disponible identificado en lotes.
- Utilidad del periodo:
  - Ventas brutas: `ventas.total` activas en rango
  - Devoluciones: `devoluciones.total` activas en rango
  - Costo de ventas: salidas de inventario ligadas a ventas activas cuya `fecha_venta` está en el rango. Una venta anulada conserva el kardex, pero deja de aportar ingreso y costo.
  - Reversión de costo por devoluciones: movimientos ligados mediante `inventario_movimientos.devolucion_id` a devoluciones activas cuya fecha está en el rango. Una devolución anulada conserva su historial, pero deja de afectar ingreso y costo.
  - `CostoVentasService` aplica el mismo cálculo neto en Reportes y Dashboard; para usuarios no administradores, Dashboard limita ventas y devoluciones a las ventas registradas por ese usuario.
  - Perdidas de inventario: ajustes negativos desde `inventario_movimientos` tipo `ajuste_inventario`
  - Pérdidas por incobrables: movimientos de crédito tipo `incobrable`
- Flujo de caja:
  - Se consolida desde `movimientos_caja` del rango seleccionado.
- Inventario valorizado:
  - Se calcula como `stock_actual * costo_promedio` por producto con stock positivo.

### Inventario inicial

- Existe el modulo `Inventario inicial` para cargar existencias de arranque sin registrar compras.
- Cada registro:
  - incrementa `stock_actual`
  - actualiza `costo_promedio` y `costo_ultimo`
  - crea lote en `inventario_lotes`
  - registra trazabilidad en `inventario_movimientos` con tipo `inventario_inicial`
- Este flujo no crea compras ni debe tratarse como gasto operativo.

### Usuarios y seguridad

- Usuario admin principal no se puede eliminar.
- Eliminacion de usuarios es logica (soft delete) y se muestran como `Eliminado`.
- Usuarios eliminados/inactivos no pueden iniciar sesion.
- En edicion de usuario, `username` se muestra como lectura en UI.
- Cambio de contrasena de un usuario desde admin cierra solo las sesiones de ese usuario (no de otros).
- Cada petición autenticada vuelve a comprobar que usuario y rol estén activos. Si alguno se desactiva, la sesión existente se invalida con HTTP 401 antes de ejecutar operaciones y frontend muestra el motivo en el login.
- El modulo `Manual` es solo de visualizacion y usa la misma pantalla para todos los roles con contenido diferenciado por rol:
  - admin: manual completo
  - operador/cajero: manual operativo resumido

### Roles

- No se puede eliminar un rol con usuarios asociados.
- Restriccion aplicada en negocio y en FK (`users.role_id` con `restrictOnDelete`).
- Orden esperado en listado: Administrador, Operador, Cajero.
- El campo `permissions.activo` controla visibilidad del menú para todos los roles: en `0` la opción no aparece.
- Para roles distintos de admin, un permiso inactivo también bloquea sus rutas SPA y APIs aunque el permiso siga asignado al rol.
- El admin mantiene acceso directo a rutas SPA y APIs incluso cuando un permiso está inactivo o no se le asignó en `permission_role`; esto sirve para administración y diagnóstico. El menú no hace ese bypass: solo muestra permisos activos que sí estén asignados al rol admin.

## 3) Configuraciones (tipos y reglas)

Codigos base:

- `nombre_empresa`: texto
- `tiempo_sesion`: entero (dias)
- `caja_alerta_faltante_monto`: entero (Q)
- `devolucion_limite_dias_cajero`: entero
- `porcentaje_utilidad_compra`: entero (%)
- `caja_aperturas_maximas_por_dia`: entero (aperturas por usuario por dia)
- `habilitar_consignacion`: entero booleano (`0` u `1`)
- `nit_empresa`: texto numérico opcional; NIT usado para identificar facturas emitidas a nombre del negocio. Puede vaciarse sin alterar el NIT histórico guardado en compras.

Reglas:

- `nombre_empresa` es obligatorio; `nit_empresa` acepta números o vacío.
- Las demas configuraciones aceptan entero.
- Enteros: minimo `0`.
- Excepcion: `devolucion_limite_dias_cajero` minimo `2`.
- Excepcion: `caja_aperturas_maximas_por_dia` minimo `1`.
- `porcentaje_utilidad_compra` define el precio sugerido en compras como `costo + porcentaje`.

Defaults actuales (seeder):

- `nombre_empresa = weltixh`
- `tiempo_sesion = 1` (dia)
- `caja_alerta_faltante_monto = 50`
- `devolucion_limite_dias_cajero = 15`
- `porcentaje_utilidad_compra = 25`
- `caja_aperturas_maximas_por_dia = 1`
- `habilitar_consignacion = 0`

## 4) Roles y alcance

- `admin`: todos los permisos
- `operador`: todo excepto modulo configuracion
- `cajero`: todo excepto configuracion y capital

Usuarios seed de prueba:

- `admin@admin.local`
- `operador@admin.local`
- `cajero@admin.local`
- Password base en seed: `password`

## 5) Comandos recomendados

Instalar:

```bash
composer install
npm install
```

Levantar entorno:

```bash
./vendor/bin/sail up -d
```

Recrear BD completa:

```bash
./vendor/bin/sail artisan migrate:fresh --seed --force
```

Seed puntual de configuraciones:

```bash
./vendor/bin/sail artisan db:seed --class=ConfiguracionSeeder --force
```

Seed de consignación y su permiso:

```bash
./vendor/bin/sail artisan db:seed --class=ConsignacionSeeder --force
```

Build frontend:

```bash
./vendor/bin/sail npm run build
```

Tests:

- Actualmente no se requiere crear ni mantener tests automatizados en este proyecto.
- Si existen archivos de prueba locales o temporales, se pueden omitir o eliminar para priorizar velocidad de entrega.

## 6) Notas operativas

- No hay triggers activos/referenciados para reglas de negocio.
- La autorizacion final siempre se valida en backend.
- Si se cambian permisos/seeders, volver a ejecutar seeders.
- Formato monetario estandar en frontend: 2 decimales, miles con coma y decimal con punto.
- Errores de API se muestran en UI (toast global + mensajes locales por pantalla).
- Zona horaria de aplicacion: `APP_TIMEZONE=America/Guatemala`.
- El reloj del header se sincroniza desde backend al cargar sesion y se resincroniza cada 30 minutos.
