# Reinstalacion en macOS con Docker Desktop y Laravel Sail

Esta guia prepara el proyecto desde un clon nuevo en macOS. No requiere instalar PHP, Composer, Node.js, MariaDB, Redis ni Mailpit en la Mac: todos se ejecutan en contenedores Docker.

El entorno final usa Laravel Sail con PHP 8.5, MariaDB, Redis y Mailpit.

## 1. Requisitos

- macOS
- Docker Desktop instalado, abierto y en ejecucion
- Git

Comprobar Docker desde Terminal:

```bash
docker --version
docker compose version
```

## 2. Entrar al proyecto

Si aun no se ha descargado el repositorio:

```bash
git clone <URL_DEL_REPOSITORIO> sistema_inventario
cd sistema_inventario
```

Si ya esta descargado, abrir Terminal en la carpeta del proyecto:

```bash
cd /ruta/al/sistema_invetario
```

## 3. Instalar dependencias PHP y Sail

En un clon nuevo no existe la carpeta `vendor/`; por ello aun no se puede ejecutar `./vendor/bin/sail`.

Sin instalar Composer en macOS, ejecutar Composer una sola vez mediante un contenedor temporal:

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-req=ext-gd
```

El flag `--ignore-platform-req=ext-gd` se usa solo en este paso inicial: la imagen temporal de Composer no trae GD, pero el runtime real de Sail del proyecto usa PHP 8.5 e instala `php8.5-gd`.

No ejecutar `composer update`, pues cambiaria las versiones bloqueadas en `composer.lock`.

Comprobar que Sail ya se instalo:

```bash
ls vendor/bin/sail
```

## 4. Crear y revisar `.env`

```bash
cp .env.example .env
```

El archivo `.env.example` ya contiene los valores locales para Sail. Verificar que esta seccion exista en `.env`:

```dotenv
APP_URL=http://localhost
APP_TIMEZONE=America/Guatemala

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

`DB_HOST=mariadb`, `REDIS_HOST=redis` y `MAIL_HOST=mailpit` son los nombres de los servicios Docker; no deben reemplazarse por `localhost`.

## 5. Iniciar los contenedores

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

En el primer arranque Docker construye el contenedor PHP 8.5 y descarga/inicia los servicios `mariadb`, `redis` y `mailpit`.

## 6. Inicializar la aplicacion

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Para desarrollo con recarga automatica, ejecutar en otra terminal y dejar el proceso abierto:

```bash
./vendor/bin/sail npm run dev
```

## 7. Accesos

- Aplicacion: http://localhost
- Mailpit: http://localhost:8025

Usuario inicial creado por los seeders:

- Usuario: `admin`
- Correo: `admin@admin.local`
- Contrasena: `password`

Cambiar la contrasena al primer ingreso.

## 8. Operacion diaria

Iniciar los servicios:

```bash
./vendor/bin/sail up -d
```

Detenerlos:

```bash
./vendor/bin/sail down
```

Ver estado y logs:

```bash
./vendor/bin/sail ps
./vendor/bin/sail logs -f laravel.test
```

Limpiar cache:

```bash
./vendor/bin/sail artisan optimize:clear
```

## 9. Reiniciar la base de datos local

El siguiente comando elimina todos los datos de la base local y vuelve a ejecutar migraciones y seeders:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Usarlo solo cuando se desea reiniciar los datos locales.
