# Reinstalacion del proyecto (WSL + Docker + Sail)

Este documento permite reinstalar y ejecutar el proyecto desde cero en otra maquina.

## 1. Requisitos

- Windows con WSL2
- Ubuntu 24.04 en WSL
- Docker Desktop con integracion WSL activa
- Git

## 2. Clonar repositorio

```bash
git clone <URL_DEL_REPO> sistema_inventario
cd sistema_inventario
```

## 3. Verificar Docker desde Ubuntu

```bash
docker --version
docker compose version
```

Si falla, habilitar integracion de Ubuntu-24.04 en Docker Desktop:
Settings > Resources > WSL Integration.

## 4. Instalar dependencias del proyecto

```bash
composer install
npm install
```

## 5. Preparar entorno

```bash
cp .env.example .env
```

Revisar variables minimas en .env (DB, APP_URL, etc.) segun entorno local.

## 6. Levantar contenedores Sail

```bash
./vendor/bin/sail up -d
```

## 7. Inicializar aplicacion

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Opcional (si no se desea seed completo):

```bash
./vendor/bin/sail artisan db:seed --class=AuthorizationSeeder
./vendor/bin/sail artisan db:seed --class=ConfiguracionSeeder
```

## 8. Compilar frontend

Desarrollo:

```bash
./vendor/bin/sail npm run dev
```

Build produccion local:

```bash
./vendor/bin/sail npm run build
```

## 9. Accesos locales

- App: http://localhost
- Mailpit: http://localhost:8025

Usuario base creado por seed:

- usuario: admin
- email: admin@admin.local
- password: password

Recomendado: cambiar password al primer ingreso.

## 10. Comandos utiles diarios

Iniciar servicios:

```bash
./vendor/bin/sail up -d
```

Detener servicios:

```bash
./vendor/bin/sail down
```

Ver logs app:

```bash
./vendor/bin/sail logs -f laravel.test
```

Correr pruebas:

```bash
./vendor/bin/sail artisan test
```

## 11. Problemas comunes

### Permisos en storage o bootstrap/cache

```bash
sudo chown -R "$USER":"$USER" .
chmod -R u+rwX storage bootstrap/cache
```

### Limpiar caches de Laravel

```bash
./vendor/bin/sail artisan optimize:clear
```

### Reinicio limpio de BD local (pierde datos)

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```
