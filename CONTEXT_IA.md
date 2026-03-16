# Contexto IA - Sistema Inventario

Este documento contiene el contexto que necesita una IA para entender el proyecto a nivel operativo, funcional y de arquitectura.

## Objetivo del sistema

Sistema SPA para inventario y POS con modulos de caja, ventas, compras, inventario, catalogos, capital y configuracion.

## Stack

- Backend: Laravel 12, Eloquent ORM, Sanctum, MariaDB.
- Frontend: Vue 3, Vue Router, Pinia, Axios, Bootstrap.
- Infra local: WSL2 Ubuntu 24.04, Docker Desktop, Laravel Sail.

## Arquitectura funcional

- API en `routes/web.php` con grupos por middleware y prefijos por modulo.
- Controladores por dominio (`Caja`, `Ventas`, `Compras`, `Capital`, `Catalogos`, etc.).
- Persistencia principal con modelos Eloquent.
- Helpers para logica reutilizable transversal.
- Services cuando la logica de negocio necesita trazabilidad o coordinacion entre modulos.

## Reglas operativas clave

### Caja

- Flujo: apertura -> movimientos -> arqueo -> cierre.
- Arqueo registra historial (se puede registrar varias veces durante turno).
- Umbral de faltante (`caja_alerta_faltante_monto`) se valida en cierre.
- Si existe arqueo, cierre usa monto contado del ultimo arqueo.
- `caja_aperturas_maximas_por_dia` controla aperturas por usuario por dia.

### Ventas

- Metodo de pago para venta nueva: solo `efectivo`.
- Precio unitario de venta: backend toma el precio actual del producto (no confiar en payload del cliente).
- Descuento se usa como mecanismo comercial controlado en UI.
- Cliente inactivo no puede usarse en nuevas ventas.

### Clientes y Proveedores

- Entidades pueden activarse/inactivarse.
- Cliente con ventas o proveedor con compras no se elimina: solo se desactiva.

### Inventario y anulaciones

- Se evita eliminacion fisica de operaciones historicas.
- Se usa estado (`activo`/`anulada`) con compensaciones de inventario y caja.

### Hora del sistema

- Timezone de app por `APP_TIMEZONE` (Guatemala: `America/Guatemala`).
- Header sincroniza hora base desde backend.
- Frontend anima reloj en vivo y resincroniza cada 30 minutos.

## Notas para agentes IA

- Priorizar cambios pequenos y seguros.
- No romper contratos API ya usados por frontend.
- Validar en backend incluso si frontend ya restringe.
- Si se cambia comportamiento funcional, actualizar documentacion correspondiente.
