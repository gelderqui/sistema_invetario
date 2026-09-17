# Sistema Inventario

Documentacion general del sistema de inventario, su estado funcional y las guias disponibles para desarrollo, instalacion y operacion.

## Estado funcional reciente

- Modulos activos en menu: Dashboard, Capital, Reportes, Caja, Ventas, Compras, Inventario, Catalogo, Configuracion.
- En caja: el arqueo registra historial; el umbral de faltante bloquea en cierre, no en arqueo.
- En cierre de caja: si existe arqueo, se usa el monto contado del ultimo arqueo.
- Configuracion nueva: `caja_aperturas_maximas_por_dia` para controlar aperturas por usuario al dia.
- En ventas: metodo de pago solo efectivo, precio tomado del producto en backend y descuento controlado por checkbox.
- En clientes: inactivo no puede vender y con historial de ventas no se elimina.
- En reportes: estado general del negocio + utilidad + flujo de caja + inventario valorizado.
- En reportes: la inversion inicial se toma del primer ingreso registrado en Capital.
- Inventario inicial: carga de existencias de arranque sin registrarlo como compra.
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
