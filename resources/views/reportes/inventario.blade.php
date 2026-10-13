<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:8px;color:#1f2937}h1{font-size:16px;margin:0}p{margin:4px 0 10px}table{width:100%;border-collapse:collapse}th{background:#1f467d;color:#fff}th,td{border:1px solid #d1d5db;padding:4px;text-align:left}.num{text-align:right}</style></head>
<body>
    <h1>Inventario actual</h1>
    <p>Generado: {{ now()->format('d/m/Y H:i') }} · Categoría: {{ $filtros['categoria'] }} · Documento: {{ $filtros['documento'] }}@if($filtros['receptor']) · Factura emitida a: {{ $filtros['receptor'] }}@endif@if($filtros['solo_bajo_stock']) · Solo bajo stock@endif@if($filtros['busqueda']) · Búsqueda: {{ $filtros['busqueda'] }}@endif</p>
    <table>
        <thead><tr><th>Producto</th><th>Categoría</th><th class="num">Stock</th><th class="num">Propio</th><th class="num">Consig.</th><th class="num">Con fact.</th><th class="num">Sin fact.</th><th class="num">Mínimo</th><th class="num">Costo prom.</th><th class="num">Precio venta</th></tr></thead>
        <tbody>
        @forelse($productos as $producto)
            <tr><td>{{ $producto['nombre'] }}@if($producto['codigo_barra'])<br><small>{{ $producto['codigo_barra'] }}</small>@endif</td><td>{{ $producto['categoria']?->nombre ?? '-' }}</td><td class="num">{{ number_format((float) $producto['stock_actual'], 0) }} {{ $producto['unidad_medida_abreviatura'] }}</td><td class="num">{{ number_format((float) $producto['stock_propio'], 0) }}</td><td class="num">{{ number_format((float) $producto['stock_consignado'], 0) }}</td><td class="num">{{ number_format((float) $producto['stock_con_factura'], 0) }}</td><td class="num">{{ number_format((float) $producto['stock_sin_factura'], 0) }}</td><td class="num">{{ number_format((float) $producto['stock_minimo'], 0) }}</td><td class="num">Q {{ number_format((float) $producto['costo_promedio'], 2) }}</td><td class="num">Q {{ number_format((float) $producto['precio_venta'], 2) }}</td></tr>
        @empty
            <tr><td colspan="10">No hay productos que coincidan con los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
