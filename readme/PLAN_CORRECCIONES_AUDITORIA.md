# Pendiente de la auditoría local

Actualizado el 16 de octubre de 2026.

## Sincronizar dependencias de PDF, Excel y tickets en localhost

Las exportaciones PDF/Excel y los tickets funcionan correctamente en el servidor de producción. No hay una corrección funcional ni un cambio de código pendiente.

En localhost, `composer.json` y `composer.lock` ya contienen las dependencias necesarias, pero la carpeta `vendor` local no está sincronizada y todavía no contiene físicamente:

- `barryvdh/laravel-dompdf`
- `dompdf/dompdf`
- `maatwebsite/excel`
- `phpoffice/phpspreadsheet`

Con Docker iniciado, ejecutar:

```bash
./vendor/bin/sail composer install
./vendor/bin/sail artisan optimize:clear
```

Después verificar únicamente en localhost:

- Exportación PDF y Excel de Compras, respetando los filtros activos.
- Exportación PDF y Excel de Inventario, respetando los filtros activos.
- Tickets PDF de ventas y devoluciones.
- Rechazo de enlaces firmados inválidos o vencidos.

Este es el único punto pendiente de la auditoría.
