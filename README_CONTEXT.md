# IA Project Context - Sistema Inventario

Documento de contexto rapido para cualquier agente IA que entre a este repositorio.

## 1) Objetivo del proyecto

SPA para inventario/POS sobre Laravel + Vue.

Estado actual principal:

- Autenticacion con Sanctum (cookie/session para SPA)
- RBAC personalizado (roles/permisos propios)
- Modulos base implementados:
  - Dashboard
  - Usuarios
  - Roles
  - Catalogos: Categorias y Productos

## 2) Stack y herramientas

Backend:

- Laravel 12
- PHP >= 8.2
- Sanctum
- MariaDB (via Sail)
- Redis, Mailpit (via Sail)

Frontend:

- Vue 3
- Vue Router 4
- Pinia
- Axios
- Bootstrap 5
- Font Awesome
- CoreUI CSS (principalmente estilos)
- Vite

Infra local:

- WSL Ubuntu 24.04
- Docker Desktop
- Laravel Sail

Paquetes relevantes adicionales:

- barryvdh/laravel-dompdf
- maatwebsite/excel

## 3) Arquitectura general

### Backend

- Rutas en routes/web.php (API y SPA entrypoint conviven aqui)
- Controladores por dominio:
  - app/Http/Controllers/AuthController.php
  - app/Http/Controllers/ConfiguracionController.php
  - app/Http/Controllers/Admin/*
  - app/Http/Controllers/Catalogos/*
- Modelos Eloquent en app/Models/*
- Migraciones en database/migrations/*
- Seeders clave:
  - AuthorizationSeeder
  - ConfiguracionSeeder

### Frontend

- Entrada en resources/js/app.js
- Router en resources/js/router.js
- Store auth en resources/js/stores/auth.js
- Navegacion en resources/js/utils/navigation.js
- Layout principal en resources/js/layouts/AppLayout.vue
- Vistas en resources/js/views/*

### Estilos

- Base en resources/css/app.css
- Componentes UI en resources/css/components.css

## 4) Seguridad y autorizacion

- Login por username + password
- Endpoints protegidos por auth:sanctum
- Control por permisos con middleware custom permission:* y role:*
- Guard de frontend revisa meta.permissions por ruta

Permisos importantes usados en UI:

- dashboard.view
- users.manage
- roles.manage
- inventory.manage

## 5) Rutas funcionales actuales (resumen)

Auth:

- POST /auth/login
- GET /auth/me
- POST /auth/logout

Configuraciones:

- GET /configuraciones/login (guest)
- GET /configuraciones/publicas (auth)

Admin:

- /admin/users (index/store/update)
- /admin/users/catalogs
- /admin/roles (index/store/update)
- /admin/permissions

Catalogos (inventory.manage):

- /catalogos/categorias (index/store/update/toggle/destroy)
- /catalogos/productos (index/store/update/toggle/destroy)

## 6) Convenciones y decisiones del proyecto

- Idioma de app en espanol (locale configurable desde tabla configuraciones)
- CRUD en frontend mayormente con modales (no paginas separadas)
- Sidebar con permisos; menu Productos tiene subitems (Categorias, Productos)
- Estilo visual actual:
  - headers de tabla y modales en azul marca
  - botones de accion en tablas con fondo azul

## 7) Flujo de arranque para IA

1. Leer README.md y readmme_reinstall.md
2. Revisar routes/web.php y resources/js/router.js
3. Revisar navigation.js y AppLayout.vue para impacto de UI
4. Si toca seguridad, revisar middleware de permisos/roles
5. Antes de cambios grandes, correr build y pruebas al final

## 8) Comandos recomendados

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

Build:

```bash
./vendor/bin/sail npm run build
```

Tests:

```bash
./vendor/bin/sail artisan test
```

## 9) Riesgos comunes

- Cambios en rutas sin actualizar guard de frontend
- Cambios en permisos sin actualizar seeders
- Estilos de Bootstrap/CoreUI sobreescribiendo clases custom
- Archivos con permisos root por uso de sudo
