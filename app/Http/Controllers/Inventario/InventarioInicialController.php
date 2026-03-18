<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioInicialController extends Controller
{
    public function index(): JsonResponse
    {
        $items = InventarioMovimiento::query()
            ->with(['producto:id,nombre'])
            ->where('tipo', 'inventario_inicial')
            ->orderByDesc('id')
            ->limit(300)
            ->get([
                'id',
                'producto_id',
                'tipo',
                'cantidad',
                'stock_anterior',
                'stock_nuevo',
                'costo_unitario',
                'referencia',
                'nota',
                'add_user',
                'created_at',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $productos = Producto::query()
            ->with('categoria:id,nombre')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'categoria_id',
                'nombre',
                'stock_actual',
                'costo_promedio',
                'control_vencimiento',
            ]);

        return response()->json([
            'data' => [
                'productos' => $productos,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'producto_id' => ['required', Rule::exists('productos', 'id')],
            'cantidad' => ['required', 'integer', 'min:1'],
            'costo_unitario' => ['required', 'numeric', 'gte:0'],
            'fecha_entrada' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_entrada'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $request->user()->id;

        $movimiento = DB::transaction(function () use ($validated, $userId) {
            $producto = Producto::query()->lockForUpdate()->findOrFail((int) $validated['producto_id']);

            if (! $producto->activo) {
                throw ValidationException::withMessages([
                    'producto_id' => ['El producto seleccionado esta inactivo.'],
                ]);
            }

            if ((bool) $producto->control_vencimiento && empty($validated['fecha_vencimiento'])) {
                throw ValidationException::withMessages([
                    'fecha_vencimiento' => ['La fecha de vencimiento es obligatoria para este producto.'],
                ]);
            }

            $cantidad = (float) toMoney((int) $validated['cantidad'], 4);
            $costoUnitario = (float) toMoney($validated['costo_unitario'], 4);
            $stockAnterior = (float) $producto->stock_actual;
            $stockNuevo = (float) toMoney($stockAnterior + $cantidad, 4);

            $costoPromedioAnterior = (float) $producto->costo_promedio;
            $totalCostoAnterior = (float) toMoney($stockAnterior * $costoPromedioAnterior, 4);
            $totalCostoEntrada = (float) toMoney($cantidad * $costoUnitario, 4);
            $totalCostoNuevo = (float) toMoney($totalCostoAnterior + $totalCostoEntrada, 4);
            $costoPromedioNuevo = $stockNuevo > 0
                ? (float) toMoney($totalCostoNuevo / $stockNuevo, 4)
                : 0.0;

            InventarioLote::query()->create([
                'producto_id' => $producto->id,
                'compra_detalle_id' => null,
                'cantidad_inicial' => $cantidad,
                'cantidad_disponible' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'fecha_entrada' => $validated['fecha_entrada'],
            ]);

            $producto->update([
                'stock_actual' => $stockNuevo,
                'costo_promedio' => $costoPromedioNuevo,
                'costo_ultimo' => $costoUnitario,
                'mod_user' => $userId,
            ]);

            return InventarioMovimiento::query()->create([
                'producto_id' => $producto->id,
                'tipo' => 'inventario_inicial',
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'costo_unitario' => $costoUnitario,
                'referencia' => 'INI-'.now()->format('YmdHis'),
                'nota' => $validated['observacion'] ?? 'Carga de inventario inicial',
                'add_user' => $userId,
            ]);
        });

        return response()->json([
            'message' => 'Inventario inicial registrado correctamente.',
            'data' => $movimiento->load('producto:id,nombre'),
        ], 201);
    }
}
