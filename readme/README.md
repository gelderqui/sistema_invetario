# Sistema Inventario

Documentacion general del sistema de inventario, su estado funcional y las guias disponibles para desarrollo, instalacion y operacion.

## Estado funcional reciente

- Modulos principales en menu: Dashboard, Agente BI, Caja Rural, Caja, Ventas, Compras, Inventario, Catalogos, Administracion y Configuraciones.
- En caja: el arqueo registra historial; el umbral de faltante bloquea en cierre, no en arqueo.
- En cierre de caja: si existe arqueo, se usa el monto contado del ultimo arqueo.
- Configuracion nueva: `caja_aperturas_maximas_por_dia` para controlar aperturas por usuario al dia.
- En ventas: pago en efectivo o crédito; el precio se toma del producto en backend y el descuento se controla por checkbox.
- Cuentas por cobrar: los abonos ingresan a una caja abierta; se pueden anular con trazabilidad mientras la caja original siga abierta.
- Una cuenta pendiente puede marcarse como incobrable, dejando su pérdida reflejada en la utilidad.
- Devoluciones: reponen inventario y resuelven el valor financiero automáticamente (reembolso en caja, reducción de deuda o ambos).
- En clientes: inactivo no puede vender y con historial de ventas no se elimina.
- En reportes: estado general del negocio, utilidad, flujo de caja, cuentas por cobrar e inventario valorizado.
- En reportes: la inversion inicial se toma del primer ingreso registrado en Capital.
- Inventario inicial: carga de existencias de arranque sin registrarlo como compra.
- Consignación opcional: al habilitar `habilitar_consignacion = 1`, Compras permite recibir mercancía sin descontar capital; al venderla se genera una cuenta por pagar al proveedor.
- Compras incluye `Cuentas por pagar`: permite registrar pagos parciales o completos de consignación desde Caja general o Banco.
- Las compras de pago inmediato pueden descontarse de Caja general, Banco o de la caja POS abierta del mismo usuario autorizado; el cierre conserva el detalle de esas compras.
- Proveedores cuenta con NIT opcional, consultable desde el mismo buscador por nombre o NIT.
- Inventario y Reportes separan mercancía propia de la consignada en custodia; el estado general resta las cuentas pendientes a proveedores.
- Formato monetario frontend estandar: 2 decimales, separador de miles con coma y decimal con punto.
- Reloj de header sincronizado desde backend al login y resincronizado cada 30 minutos.

## Mapa de documentacion

- `README.md`: descripcion general y estado funcional del sistema.
- `README_ARQUITECTURA.md`: stack, estructura backend/frontend, autenticacion y servicios.
- `install/README_INSTALL.md`: como se creo y se levanta el proyecto base con WSL, Docker y Sail.
- `install/readme_reintall_windows.md`: guia para reinstalar el proyecto en Windows.
- `install/readme_reintall_mac.md`: guia para reinstalar el proyecto en macOS.
- `README_CONTEXT.md`: contexto operativo y reglas funcionales para que una IA entienda el sistema.
- `README_PROGRAMACION.md`: convenciones y estilo de programacion del proyecto.
- `README_BASE_DATOS.md`: tablas y relaciones actuales de la base de datos.
- `README_REGLAS_INQUEBRANTABLES.md`: reglas de negocio que no deben romperse.
- `README_FLUJOS_CRITICOS.md`: flujos operativos criticos punta a punta.
- `manual/README_MANUAL_OPERATIVO.md`: manual operativo del sistema.
- `manual/README_MANUAL_USUARIO.md`: manual funcional para usuario final (pantallas, botones y flujo).
