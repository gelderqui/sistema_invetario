<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;

class InventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inventario = $this->datosInventario($request);

        return response()->json([
            'data' => $inventario['productos'],
            'categorias' => $inventario['categorias'],
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $inventario = $this->datosInventario($request);

        return Excel::download(new class($inventario['productos']) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private readonly Collection $productos) {}
            public function collection(): Collection { return $this->productos; }
            public function headings(): array { return ['Producto', 'Código de barra', 'Categoría', 'Stock', 'Unidad', 'Propio', 'Consignación', 'Con factura Mi NIT', 'Sin factura fiscal', 'Stock mínimo', 'Costo promedio', 'Precio venta']; }
            public function map($producto): array {
                return [
                    $producto['nombre'], $producto['codigo_barra'], $producto['categoria']?->nombre,
                    $producto['stock_actual'], $producto['unidad_medida_abreviatura'], $producto['stock_propio'],
                    $producto['stock_consignado'], $producto['stock_con_factura'], $producto['stock_sin_factura'],
                    $producto['stock_minimo'], $producto['costo_promedio'], $producto['precio_venta'],
                ];
            }
        }, 'inventario-'.now()->format('Ymd').'.xlsx');
    }

    public function exportarPdf(Request $request)
    {
        $inventario = $this->datosInventario($request);

        return Pdf::loadView('reportes.inventario', [
            'productos' => $inventario['productos'],
            'filtros' => $this->etiquetasFiltrosInventario($request),
        ])->setPaper('letter', 'landscape')->stream('inventario-'.now()->format('Ymd').'.pdf');
    }

    private function datosInventario(Request $request): array
    {
        $validated = $this->validarFiltrosInventario($request);

        $query = Producto::query()
            ->with(['categoria:id,nombre', 'unidadMedida:id,nombre,abreviatura'])
            ->orderBy('nombre');

        if ((bool) ($validated['solo_bajo_stock'] ?? false)) {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        if (! empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['categoria_id'])) {
            $query->where('categoria_id', (int) $validated['categoria_id']);
        }

        if (! empty($validated['tipo_documento'])) {
            $lotesDocumento = $this->consultaLotesPorDocumento($validated)->select('producto_id');
            $query->whereIn('id', $lotesDocumento);
        }

        $productosBase = $query->get([
            'id',
            'categoria_id',
            'nombre',
            'codigo_barra',
            'stock_actual',
            'stock_minimo',
            'costo_promedio',
            'precio_venta_promedio',
            'costo_ultimo',
            'precio_venta',
            'unidad_medida_id',
            'activo',
            'updated_at',
        ]);
        $stockConsignado = InventarioLote::query()->where('cantidad_disponible', '>', 0)->where('es_consignacion', true)
            ->selectRaw('producto_id, SUM(cantidad_disponible) as cantidad')->groupBy('producto_id')->pluck('cantidad', 'producto_id');
        $stockFiscal = InventarioLote::query()
            ->where('cantidad_disponible', '>', 0)
            ->selectRaw("producto_id, SUM(CASE WHEN respaldo_fiscal = 'con_factura' THEN cantidad_disponible ELSE 0 END) as con_factura, SUM(CASE WHEN respaldo_fiscal = 'sin_factura' THEN cantidad_disponible ELSE 0 END) as sin_factura")
            ->groupBy('producto_id')
            ->get()
            ->keyBy('producto_id');
        $productos = $productosBase->map(function (Producto $producto) use ($stockConsignado, $stockFiscal): array {
            $fiscal = $stockFiscal->get($producto->id);
            return [
                'id' => $producto->id,
                'categoria_id' => $producto->categoria_id,
                'categoria' => $producto->categoria,
                'nombre' => $producto->nombre,
                'codigo_barra' => $producto->codigo_barra,
                'stock_actual' => $producto->stock_actual,
                'stock_consignado' => (float) ($stockConsignado->get($producto->id) ?? 0),
                'stock_propio' => toMoney((float) $producto->stock_actual - (float) ($stockConsignado->get($producto->id) ?? 0), 4),
                'stock_con_factura' => toMoney((float) ($fiscal?->con_factura ?? 0), 4),
                'stock_sin_factura' => toMoney((float) ($fiscal?->sin_factura ?? 0), 4),
                'stock_minimo' => $producto->stock_minimo,
                'costo_promedio' => $producto->costo_promedio,
                'precio_venta_promedio' => $producto->precio_venta_promedio,
                'costo_ultimo' => $producto->costo_ultimo,
                'precio_venta' => $producto->precio_venta,
                'unidad_medida_abreviatura' => $producto->unidadMedida?->abreviatura,
                'activo' => $producto->activo,
                'updated_at' => $producto->updated_at,
            ];
        });

        $categorias = Categoria::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return compact('productos', 'categorias');
    }

    private function validarFiltrosInventario(Request $request): array
    {
        return $request->validate([
            'solo_bajo_stock' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'tipo_documento' => ['nullable', Rule::in(['sin_documento', 'recibo', 'factura'])],
            'factura_receptor' => ['nullable', Rule::in(['cf', 'nit_empresa'])],
        ]);
    }

    private function consultaLotesPorDocumento(array $filtros)
    {
        $lotes = InventarioLote::query()->where('cantidad_disponible', '>', 0);
        $tipoDocumento = $filtros['tipo_documento'] ?? null;

        if ($tipoDocumento === 'sin_documento') {
            return $lotes->where(function ($query): void {
                $query->whereDoesntHave('compraDetalle.compra')
                    ->orWhereHas('compraDetalle.compra', function ($compra): void {
                        $compra->where('tipo_documento', 'sin_documento')->orWhereNull('tipo_documento');
                    });
            });
        }

        return $lotes->whereHas('compraDetalle.compra', function ($compra) use ($tipoDocumento, $filtros): void {
            $compra->where('tipo_documento', $tipoDocumento);

            if ($tipoDocumento === 'factura' && ! empty($filtros['factura_receptor'])) {
                $compra->where('factura_receptor', $filtros['factura_receptor']);
            }
        });
    }

    private function etiquetasFiltrosInventario(Request $request): array
    {
        $filtros = $this->validarFiltrosInventario($request);
        $tipoDocumento = $filtros['tipo_documento'] ?? null;

        return [
            'busqueda' => $filtros['search'] ?? null,
            'categoria' => ! empty($filtros['categoria_id'])
                ? (Categoria::query()->find($filtros['categoria_id'])?->nombre ?? 'Categoría seleccionada')
                : 'Todas',
            'documento' => match ($tipoDocumento) { 'sin_documento' => 'Sin documento', 'recibo' => 'Recibo', 'factura' => 'Factura', default => 'Todos' },
            'receptor' => $tipoDocumento === 'factura'
                ? match ($filtros['factura_receptor'] ?? null) { 'cf' => 'CF', 'nit_empresa' => 'Mi NIT', default => 'CF y Mi NIT' }
                : null,
            'solo_bajo_stock' => (bool) ($filtros['solo_bajo_stock'] ?? false),
        ];
    }

    public function movimientos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
        ]);

        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre',
                'compra:id,numero',
                'lote:id,fecha_entrada,fecha_vencimiento',
            ])
            ->orderByDesc('id');

        if (! empty($validated['fecha_desde'])) {
            $query->whereDate('created_at', '>=', (string) $validated['fecha_desde']);
        }

        if (! empty($validated['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', (string) $validated['fecha_hasta']);
        }

        if (! empty($validated['categoria_id'])) {
            $categoriaId = (int) $validated['categoria_id'];
            $query->whereHas('producto', fn ($q) => $q->where('categoria_id', $categoriaId));
        }

        if (! empty($validated['producto_id'])) {
            $query->where('producto_id', (int) $validated['producto_id']);
        }

        $movimientos = $query->limit(200)->get([
            'id',
            'producto_id',
            'compra_id',
            'lote_id',
            'tipo',
            'cantidad',
            'stock_anterior',
            'stock_nuevo',
            'costo_unitario',
            'referencia',
            'nota',
            'created_at',
        ]);

        $categorias = Categoria::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $productosQuery = Producto::query()
            ->where('activo', true)
            ->whereHas('movimientosInventario')
            ->orderBy('nombre');

        if (! empty($validated['categoria_id'])) {
            $productosQuery->where('categoria_id', (int) $validated['categoria_id']);
        }

        $productos = $productosQuery->get(['id', 'nombre']);

        return response()->json([
            'data' => $movimientos,
            'categorias' => $categorias,
            'productos' => $productos,
        ]);
    }

    public function alertas(Request $request): JsonResponse
    {
        $hoy = Carbon::today()->toDateString();

        $bajoStock = Producto::query()
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'stock_actual',
                'stock_minimo',
            ]);

        $lotesActivosConVencimiento = InventarioLote::query()
            ->with(['producto:id,nombre,control_vencimiento,dias_alerta_vencimiento'])
            ->where('cantidad_disponible', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->whereHas('producto', fn ($q) => $q->where('control_vencimiento', true))
            ->orderBy('fecha_vencimiento')
            ->get(['id', 'producto_id', 'cantidad_disponible', 'fecha_vencimiento']);

        $porVencer = $lotesActivosConVencimiento
            ->filter(function (InventarioLote $lote) use ($hoy): bool {
                if (! $lote->fecha_vencimiento || ! $lote->producto) {
                    return false;
                }

                $fechaVencimiento = Carbon::parse((string) $lote->fecha_vencimiento)->toDateString();
                if ($fechaVencimiento < $hoy) {
                    return false;
                }

                $diasRestantes = Carbon::parse((string) $hoy)->diffInDays(Carbon::parse((string) $fechaVencimiento));

                return $diasRestantes <= (int) $lote->producto->dias_alerta_vencimiento;
            })
            ->values()
            ->map(fn (InventarioLote $lote): array => [
                'lote_id' => $lote->id,
                'producto_id' => $lote->producto_id,
                'producto_nombre' => $lote->producto?->nombre,
                'cantidad_disponible' => $lote->cantidad_disponible,
                'fecha_vencimiento' => $lote->fecha_vencimiento,
                'dias_alerta_vencimiento' => (int) ($lote->producto?->dias_alerta_vencimiento ?? 0),
            ]);

        $vencidos = $lotesActivosConVencimiento
            ->filter(function (InventarioLote $lote) use ($hoy): bool {
                if (! $lote->fecha_vencimiento) {
                    return false;
                }

                return Carbon::parse((string) $lote->fecha_vencimiento)->toDateString() < $hoy;
            })
            ->values()
            ->map(fn (InventarioLote $lote): array => [
                'lote_id' => $lote->id,
                'producto_id' => $lote->producto_id,
                'producto_nombre' => $lote->producto?->nombre,
                'cantidad_disponible' => $lote->cantidad_disponible,
                'fecha_vencimiento' => $lote->fecha_vencimiento,
            ]);

        return response()->json([
            'data' => [
                'bajo_stock' => $bajoStock,
                'por_vencer' => $porVencer,
                'vencidos' => $vencidos,
            ],
        ]);
    }
}
