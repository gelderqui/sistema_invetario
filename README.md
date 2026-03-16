# Sistema Inventario - Instalacion con WSL + Docker Desktop + Laravel Sail

Guia rapida para levantar el proyecto en Windows usando WSL2 (Ubuntu) y Docker Desktop.

## Estado funcional reciente

- Modulos activos en menu: Dashboard, Capital, Caja, Ventas, Compras, Inventario, Catalogo, Configuracion.
- En caja: el arqueo registra historial; el umbral de faltante bloquea en cierre, no en arqueo.
- En cierre de caja: si existe arqueo, se usa el monto contado del ultimo arqueo.
- Configuracion nueva: `caja_aperturas_maximas_por_dia` para controlar aperturas por usuario al dia.
- Formato monetario frontend estandar: 2 decimales, separador de miles con coma y decimal con punto.

Para detalle operativo y reglas de negocio:

- Ver `README_MANUAL_USUARIO.md`
- Ver `README_CONTEXT.md`
- Ver `README_REINSTALL.md`

## 1. Prerrequisitos en Windows

Instalar Docker Desktop.
Actualizar WSL e instalar Ubuntu (ejecutar en PowerShell como administrador):

```bash
wsl -l -v
wsl --install -d Ubuntu-24.04
```

Reiniciar Windows si lo solicita.

Abrir Ubuntu:

```bash
wsl -d Ubuntu-24.04
```

## 2. Verificar Docker dentro de Ubuntu

```bash
docker --version
docker compose version
```

Si Docker no responde en Ubuntu:

1. Abrir Docker Desktop en Windows.
2. Ir a Settings > Resources > WSL Integration.
3. Activar la distro Ubuntu-24.04.
4. Volver a Ubuntu y probar otra vez `docker --version`.

## 3. Instalar utilidades basicas en Ubuntu

```bash
sudo apt update
sudo apt install -y curl git unzip
```

## 4. Crear proyecto Laravel con Sail

```bash
mkdir -p ~/projects
cd ~/projects
curl -s "https://laravel.build/sistema_inventario?with=mariadb,redis,mailpit" | bash
```

Entrar al proyecto:

```bash
cd sistema_inventario
```

## 5. Configurar version de PHP en Sail

Intentar con PHP 8.5:

```bash
./vendor/bin/sail artisan sail:install --with=mariadb,redis,mailpit --php=8.5
```

Si no esta soportada, usar 8.4:

```bash
./vendor/bin/sail artisan sail:install --with=mariadb,redis,mailpit --php=8.4
```

## 6. Levantar e inicializar el entorno

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

Validar servicios:

```bash
./vendor/bin/sail ps
```

## 7. Accesos

- Proyecto: http://localhost
- Mailpit: http://localhost:8025

## 8. Correccion de permisos (si aparece Permission denied)

Si ves errores como `laravel.log could not be opened` o `.env permission denied`, ejecutar dentro de la carpeta del proyecto:

```bash
sudo chown -R "$USER":"$USER" .
chmod -R u+rwX storage bootstrap/cache
```

Despues, volver a probar:

```bash
./vendor/bin/sail artisan key:generate
```

Nota: evita usar `sudo` con comandos de Sail para no volver a dejar archivos en root.

## 9. Alias para usar `sail` en vez de `./vendor/bin/sail`

Configurar alias permanente (sin abrir vim):

```bash
echo 'alias sail="./vendor/bin/sail"' >> ~/.bashrc
source ~/.bashrc
```

Probar dentro del proyecto:

```bash
cd /home/sistemas/sistema_inventario
sail --version
```

## 10. Operacion diaria

Iniciar:

```bash
sail up -d
```

Detener:

```bash
sail down
```

Logs aplicacion:

```bash
sail logs -f laravel.test
```

Logs base de datos:

```bash
sail logs -f mariadb
```
