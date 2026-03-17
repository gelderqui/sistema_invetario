# README Context - Sistema Inventario

Este documento es el contexto principal para IA: resume arquitectura, reglas operativas y decisiones funcionales para entender el sistema antes de modificar codigo.

## 1) Objetivo del proyecto

SPA de inventario + POS sobre Laravel + Vue, con control de caja, devoluciones, ajustes y capital.

Estado funcional actual:

- Autenticacion SPA con Sanctum (cookie + sesion)
- RBAC propio por rol/permisos
- Modulos activos y orden de menu:
  - Dashboard
  - Capital
  - Caja
  - Ventas
  - Compras
  - Inventario
  - Catalogo
  - Configuracion
  - Manual (solo admin)

## 2) Stack y herramientas

Backend:

- Laravel 12
- PHP >= 8.2
- Sanctum
- MariaDB (Sail)
- barryvdh/laravel-dompdf

Frontend:

- Vue 3
- Vue Router 4
- Pinia
- Axios
- Bootstrap 5
- Font Awesome
- Vite

Infra local:

- WSL Ubuntu 24.04
- Docker Desktop
- Laravel Sail

## 3) Arquitectura actual

### Backend

- Rutas API + SPA entrypoint en `routes/web.php`
- Controladores clave:
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/ConfiguracionController.php`
  - `app/Http/Controllers/Admin/UserManagementController.php`
  - `app/Http/Controllers/Admin/RoleManagementController.php`
  - `app/Http/Controllers/Caja/CajaController.php`
  - `app/Http/Controllers/Ventas/VentaController.php`
  - `app/Http/Controllers/Ventas/DevolucionController.php`
  - `app/Http/Controllers/Capital/CapitalController.php`
  - `app/Http/Controllers/Compras/CompraController.php`
  - `app/Http/Controllers/Inventario/*`
- Middleware de permisos:
  - `app/Http/Middleware/CheckPermission.php`

### Frontend

- Shell principal: `resources/js/AppLayout.vue`
- Router: `resources/js/router.js`
- Store auth: `resources/js/stores/auth.js`
- Vistas principales:
  - `resources/js/components/CajaView.vue`
  - `resources/js/components/VentasView.vue`
  - `resources/js/components/HistorialVentasView.vue`
  - `resources/js/components/DevolucionesView.vue`
  - `resources/js/components/ComprasView.vue`
  - `resources/js/components/InventarioAlertasView.vue`
  - `resources/js/components/CapitalView.vue`
  - `resources/js/components/UsersView.vue`
  - `resources/js/components/ConfiguracionesView.vue`
  - `resources/js/components/TicketReceiptModal.vue`

## 4) Reglas funcionales vigentes

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

### Ventas, devoluciones y anulaciones

- No se eliminan ventas/compras/devoluciones fisicamente para anular.
- Se usa `estado` (`activo` o `anulada`) y se registra compensacion en inventario/caja.
- En ventas nuevas, el usuario debe tener caja abierta para poder registrar la venta.
- En ventas nuevas, el metodo de pago permitido es solo `efectivo`.
- En ventas nuevas, el precio unitario se toma siempre del producto en backend (no se acepta override por request).
- El descuento en UI se habilita manualmente por checkbox.
- En historial de ventas, `admin` puede ver todas las ventas y filtrar por usuario; los demas roles solo ven sus propios registros.

### Clientes

- Cliente inactivo no puede usarse en nuevas ventas.
- Cliente con ventas registradas no puede eliminarse; solo puede desactivarse.

Regla de fecha:

- Venta del mismo dia: se maneja con anulacion de venta.
- Venta de dias anteriores: se maneja con devolucion.

Compensaciones:

- Anulacion de venta:
  - Inventario: movimiento `anulacion_venta` (+stock)
  - Caja (si fue efectivo): movimiento `anulacion_venta` (egreso)
- Anulacion de compra:
  - Inventario: movimiento `anulacion_compra` (-stock), con validacion de lotes no consumidos
- Anulacion de devolucion:
  - Inventario: movimiento `anulacion_devolucion` (-stock)

### Recibos PDF

- Venta y devolucion generan ticket PDF (tamano pequeno) con opcion de imprimir.
- Disponible al guardar y desde historial.

### Usuarios y seguridad

- Usuario admin principal no se puede eliminar.
- Eliminacion de usuarios es logica (soft delete) y se muestran como `Eliminado`.
- Usuarios eliminados/inactivos no pueden iniciar sesion.
- En edicion de usuario, `username` se muestra como lectura en UI.
- Cambio de contrasena de un usuario desde admin cierra solo las sesiones de ese usuario (no de otros).
- El modulo `Manual de Usuario` es solo de visualizacion y su acceso esta restringido a rol admin mediante permiso `manual_usuario`.

### Roles

- No se puede eliminar un rol con usuarios asociados.
- Restriccion aplicada en negocio y en FK (`users.role_id` con `restrictOnDelete`).
- Orden esperado en listado: Administrador, Operador, Cajero.

## 5) Configuraciones (tipos y reglas)

Codigos base:

- `nombre_empresa`: texto
- `tiempo_sesion`: entero (dias)
- `caja_alerta_faltante_monto`: entero (Q)
- `devolucion_limite_dias_cajero`: entero
- `porcentaje_utilidad_compra`: entero (%)
- `caja_aperturas_maximas_por_dia`: entero (aperturas por usuario por dia)

Reglas:

- Solo `nombre_empresa` acepta texto.
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

## 6) Roles y alcance

- `admin`: todos los permisos
- `operador`: todo excepto modulo configuracion
- `cajero`: todo excepto configuracion y capital

Usuarios seed de prueba:

- `admin@admin.local`
- `operador@admin.local`
- `cajero@admin.local`
- Password base en seed: `password`

## 7) Comandos recomendados

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

Build frontend:

```bash
./vendor/bin/sail npm run build
```

Tests:

- Actualmente no se requiere crear ni mantener tests automatizados en este proyecto.
- Si existen archivos de prueba locales o temporales, se pueden omitir o eliminar para priorizar velocidad de entrega.

## 8) Notas operativas

- No hay triggers activos/referenciados para reglas de negocio.
- La autorizacion final siempre se valida en backend.
- Si se cambian permisos/seeders, volver a ejecutar seeders.
- Formato monetario estandar en frontend: 2 decimales, miles con coma y decimal con punto.
- Errores de API se muestran en UI (toast global + mensajes locales por pantalla).
- Zona horaria de aplicacion: `APP_TIMEZONE=America/Guatemala`.
- El reloj del header se sincroniza desde backend al cargar sesion y se resincroniza cada 30 minutos.
