# IA Project Context - Sistema Inventario

Documento de contexto rapido para cualquier agente IA que entre a este repositorio.

## 1) Objetivo del proyecto

SPA para inventario/POS sobre Laravel + Vue.

Estado funcional actual:

- Autenticacion SPA con Sanctum (cookie + sesion)
- RBAC personalizado por rol/permisos (tabla propia)
- Modulos activos:
  - Dashboard
  - Configuracion: Usuarios, Roles, Configuraciones
  - Catalogos: Categorias, Productos, Proveedores, Clientes
  - Compras
  - Inventario

## 2) Stack y herramientas

Backend:

- Laravel 12
- PHP >= 8.2
- Sanctum
- MariaDB (Sail)
- Redis, Mailpit (Sail)

Frontend:

- Vue 3
- Vue Router 4
- Pinia
- Axios
- Bootstrap 5
- Font Awesome
- CoreUI CSS
- Vite

Infra local:

- WSL Ubuntu 24.04
- Docker Desktop
- Laravel Sail

## 3) Arquitectura actual

### Backend

- Rutas API + SPA entrypoint en `routes/web.php`
- Controladores principales:
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/ConfiguracionController.php`
  - `app/Http/Controllers/Admin/*`
  - `app/Http/Controllers/Catalogos/*`
  - `app/Http/Controllers/Compras/*`
  - `app/Http/Controllers/Inventario/*`
- Recurso de usuario autenticado para frontend:
  - `app/Http/Resources/AuthenticatedUserResource.php`
- Middleware de autorizacion:
  - `app/Http/Middleware/CheckPermission.php`

### Frontend

- Entrada Vue: `resources/js/app.js`
- Componente raiz (shell): `resources/js/AppLayout.vue`
  - contiene sidebar, header, modal de cambio de contrasena, y `<router-view />`
- Router: `resources/js/router.js`
  - hace bootstrap de sesion llamando `/auth/me`
  - controla solo `requiresAuth` y `guestOnly`
  - la autorizacion fina se deja al backend (middleware)
- Store Pinia (minimalista): `resources/js/stores/auth.js`
  - estado compartido: `user`, `initialized`
  - acciones: `setUser`, `clearUser`, `setInitialized`
- Componentes protegidos: `resources/js/components/*`
- Componentes publicos: `resources/js/components_public/*`
- La carpeta `resources/js/utils` fue eliminada por no uso actual.

### Estilos CSS

Estrategia vigente (confirmada):

- No se estan usando bloques `<style>` dentro de archivos `.vue`.
- El estilo esta centralizado en `resources/css`:
  - `app.css`: base global e imports de CSS modulares
  - `components.css`: estilos compartidos/reutilizables (botones, modales, thead, sidebar)
  - `catalogos.css`: clases de catalogos
  - `compras.css`: clases de compras
  - `inventario.css`: clases de inventario

Convencion recomendada:

- Si una regla aplica en varias pantallas o es UI compartida, va a `components.css`.
- Si una regla aplica a un dominio (catalogos/compras/inventario), va a su archivo de dominio.
- Evitar CSS embebido en componentes salvo casos excepcionales.

## 4) Flujo auth/permisos/menu

1. Al cargar app, router ejecuta guard global.
2. Si no esta inicializado, hace `GET /auth/me`.
3. El backend responde usuario + rol + permisos (resource autenticado).
4. El store guarda `user` y `initialized`.
5. `AppLayout.vue` arma el menu desde `authStore.user.permissions`.
6. Si el usuario navega a endpoint sin permiso, backend responde 403 y axios interceptor redirige a `/error`.

Notas:

- El menu se pinta por permisos efectivos del usuario.
- Actualmente existe una definicion de grupos en frontend (`catalogos`, `configuracion`) en `AppLayout.vue`.
- Se inicio trabajo para mover metadata de grupo a BD en permisos (`module_label`, `module_icono`) via migracion/seeder.

## 5) Rutas funcionales (resumen)

Auth:

- `POST /auth/login`
- `GET /auth/me`
- `POST /auth/logout`
- `PUT /auth/password`

Configuraciones publicas:

- `GET /configuraciones/get/login` (guest)
- `GET /configuraciones/get/publicas` (auth)

Modulo configuracion (auth + permission + ajax):

- `configuracion/usuarios/*`
- `configuracion/roles/*`
- `configuracion/permissions/get`
- `configuracion/configuraciones/*`

Catalogos:

- `catalogos/categorias/*`
- `catalogos/productos/*`
- `catalogos/proveedores/*`
- `catalogos/clientes/*`

Operativo:

- `compras/*`
- `inventario/*`

## 6) Cambios recientes importantes

- Reestructura frontend:
  - `AppLayout.vue` movido a `resources/js/AppLayout.vue`
  - vistas movidas a `resources/js/components` y `resources/js/components_public`
- Eliminadas carpetas/archivos obsoletos:
  - `resources/js/views`
  - `resources/js/layouts`
  - `resources/js/utils`
- Store auth simplificado (Pinia solo estado compartido)
- Login movido a `LoginView.vue`; logout/password a `AppLayout.vue`
- Router simplificado sin chequeo de permisos por path en frontend
- Configuraciones:
  - removido `locale` del seeder y del endpoint publico
  - defaults actuales: `nombre_empresa=weltixh`, `tiempo_sesion=120`
- Carpeta de traducciones movida a `resources/lang`

## 7) Comandos recomendados

Instalar:

```bash
composer install
npm install
```

Levantar entorno:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

Aplicar solo configuraciones:

```bash
./vendor/bin/sail artisan db:seed --class=ConfiguracionSeeder
```

Build:

```bash
./vendor/bin/sail npm run build
```

Tests:

```bash
./vendor/bin/sail artisan test
```

## 8) Riesgos comunes

- Cambiar rutas backend sin ajustar router/frontend
- Cambiar permisos/seeders sin refrescar datos (`migrate --seed` o seeder puntual)
- Confiar solo en frontend para autorizacion (la verdad esta en backend)
- Mezclar estilos por componente en vez de mantener CSS centralizado
- Archivos con permisos root por uso de sudo en WSL
