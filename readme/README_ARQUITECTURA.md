# Arquitectura del sistema

## Stack y herramientas

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

## Arquitectura actual

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
  - `resources/js/components/InventarioInicialView.vue`
  - `resources/js/components/ReportesView.vue`
  - `resources/js/components/CapitalView.vue`
  - `resources/js/components/UsersView.vue`
  - `resources/js/components/ConfiguracionesView.vue`
  - `resources/js/components/TicketReceiptModal.vue`

### Autenticación y autorización

- Autenticación SPA con Sanctum mediante cookie y sesión.
- RBAC propio basado en roles y permisos.
- La autorización final siempre se valida en el backend.
- El frontend muestra módulos y acciones según los permisos del usuario.

### Persistencia y servicios

- MariaDB almacena la información transaccional del sistema.
- Laravel Sail ejecuta la aplicación y sus servicios mediante Docker Compose.
- DomPDF genera tickets de ventas y devoluciones.
- La ruta pública para DomPDF usa fallbacks para entornos locales y hosting compartido.

### Módulos principales

- Dashboard
- Capital
- Reportes
- Caja
- Ventas
- Compras
- Inventario
- Catálogo
- Configuración
- Manual
