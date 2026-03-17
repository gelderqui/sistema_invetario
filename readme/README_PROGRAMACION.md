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

Politica de acceso a datos:

- Evitar `DB::table`, `join`, `whereRaw`, `orderByRaw` y SQL crudo en controladores de negocio siempre que exista alternativa con Eloquent.
- Excepciones permitidas: operaciones de infraestructura sin modelo Eloquent directo (ejemplo: limpieza de tabla de sesiones por nombre dinamico de configuracion).
- Si una excepcion es necesaria, documentar la razon en PR/README y mantener la consulta parametrizada.

Politica de validaciones:

- Prioridad alta: toda validacion de seguridad y negocio debe existir en backend.
- Frontend replica validaciones de UX (required, rangos, formato) para mejor experiencia, pero nunca reemplaza backend.
- En filtros de fecha usar regla backend `after_or_equal` para evitar rangos invalidos.

Politica de modelos:

- Todo modelo de dominio debe definir `fillable` para controlar asignacion masiva.
- Evitar `$guarded = []` en modelos del sistema.

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
- `README_CONTEXT.md` (contexto operativo para IA)
- `README_REGLAS_INQUEBRANTABLES.md` (si cambia una regla de negocio critica)
- `README_FLUJOS_CRITICOS.md` (si cambia un flujo operativo punta a punta)
- Este archivo si cambia la forma de programar o arquitectura.
