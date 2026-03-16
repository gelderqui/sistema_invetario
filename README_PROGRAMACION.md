# README Programacion

Este documento define como se programa en este proyecto para mantener consistencia tecnica.

## Convenciones generales

- Frontend en Vue 3.
- Backend en Laravel con Eloquent ORM.
- Validaciones criticas siempre en backend.
- Reglas de negocio reutilizables en helpers o services segun impacto.

## Estructura Vue

Orden preferido en componentes Vue:

1. `template`
2. `script`
3. `style`

Regla del proyecto:

- Priorizar estilos globales en archivos CSS compartidos.
- Evitar meter CSS grande dentro de cada componente si ya existe estilo global.

## Rutas Laravel

- Organizar rutas con `prefix` por modulo.
- Mantener separacion por contexto funcional (`ventas`, `caja`, `capital`, `inventario`, etc.).
- Usar middleware de autenticacion, permisos y AJAX donde corresponda.

## Controladores

- Usar Eloquent para consultas y persistencia.
- Validar request en controlador o Request class.
- Mantener respuestas JSON consistentes (`message`, `data`).

## Reutilizacion de logica

- Si una funcion se reutiliza en varios modulos: mover a helper.
- Si la logica es importante para auditoria o negocio transversal: mover a service.
- Evitar duplicar reglas criticas en multiples controladores.

## Manejo de errores

- Backend debe devolver errores claros para UI.
- Frontend debe mostrar errores globales y locales segun accion.

## Documentacion al entregar cambios

Cada cambio funcional debe actualizar:

- `README_MANUAL_USUARIO.md` (impacto al usuario final)
- `CONTEXT_IA.md` (contexto operativo para IA)
- Este archivo si cambia la forma de programar o arquitectura.
