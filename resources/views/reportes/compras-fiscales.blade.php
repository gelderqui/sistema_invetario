<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:9px;color:#1f2937}h1{font-size:16px;margin:0}p{margin:4px 0 12px}table{width:100%;border-collapse:collapse}th{background:#1f467d;color:#fff}th,td{border:1px solid #d1d5db;padding:5px;text-align:left}.num{text-align:right}</style></head>
<body>
    <h1>Listado de compras</h1>
    <p>{{ $empresa }}@if($nitEmpresa) · NIT: {{ $nitEmpresa }}@endif · Generado: {{ now()->format('d/m/Y H:i') }}</p>
    <p>Proveedor: {{ $filtros['proveedor'] }} · Documento: {{ $filtros['documento'] }}@if($filtros['receptor']) · Factura emitida a: {{ $filtros['receptor'] }}@endif</p>
    <table>
        <thead><tr><th>Fecha</th><th>Compra</th><th>Proveedor</th><th>Documento</th><th>NIT proveedor</th><th>Serie/DTE</th><th>Número</th><th class="num">Total</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse($compras as $compra)
            <tr><td>{{ optional($compra->fecha_compra)->format('d/m/Y') }}</td><td>{{ $compra->numero }}</td><td>{{ $compra->proveedor?->nombre }}</td><td>{{ $compra->tipo_documento === 'factura' ? 'Factura · '.($compra->factura_receptor === 'nit_empresa' ? 'Mi NIT' : 'CF') : ($compra->tipo_documento === 'recibo' ? 'Recibo' : 'Sin documento') }}</td><td>{{ $compra->nit_proveedor }}</td><td>{{ $compra->serie_documento }}</td><td>{{ $compra->numero_documento }}</td><td class="num">Q {{ number_format((float) $compra->total, 2) }}</td><td>{{ $compra->estado }}</td></tr>
        @empty
            <tr><td colspan="9">No hay compras que coincidan con los filtros seleccionados.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
