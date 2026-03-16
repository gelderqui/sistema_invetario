<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AjusteInventario;
use App\Models\InventarioLote;
use App\Models\InventarioMovimiento;
use App\Models\MotivoAjuste;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AjusteInventarioController extends Controller
{
    public function index(): JsonResponse
    {
        $items = AjusteInventario::query()
            ->with([
                'producto:id,nombre',
                'motivo:id,nombre,tipo',
                'usuario:id,name',
                'lote:id,fecha_vencimiento,fecha_entrada',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'producto_id',
                'cantidad',
                'motivo_id',
                'usuario_id',
                'lote_id',
                'fecha',
                'observacion',
                'created_at',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }

    public function catalogs(): JsonResponse
    {
        $productos = Producto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'stock_actual',
                'costo_promedio',
                'control_vencimiento',
                'dias_alerta_vencimiento',
            ]);

        $motivos = MotivoAjuste::query()->orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        return response()->json([
            'data' => [
                'productos' => $productos,
                'motivos' => $motivos,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'producto_id' => ['required', Rule::exists('productos', 'id')],
            'cantidad' => ['required', 'integer', 'not_in:0'],
            'motivo_id' => ['required', Rule::exists('motivos_ajuste', 'id')],
            'lote_id' => ['nullable', Rule::exists('inventario_lotes', 'id')],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $request->user()->id;

        $ajuste = DB::transaction(function () use ($validated, $userId) {
            $producto = Producto::query()->lockForUpdate()->findOrFail($validated['producto_id']);
            $motivo = MotivoAjuste::query()->findOrFail($validated['motivo_id']);

            $cantidad = toMoney((int) $validated['cantidad'], 4);

            if ($motivo->tipo === 'entrada' && $cantidad < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => ['El motivo seleccionado solo permite cantidad positiva.'],
                ]);
            }

            if ($motivo->tipo === 'salida' && $cantidad > 0) {
                throw ValidationException::withMessages([
                    'cantidad' => ['El motivo seleccionado solo permite cantidad negativa.'],
                ]);
            }

            $stockAnterior = (float) $producto->stock_actual;
            $stockNuevo = toMoney($stockAnterior + $cantidad, 4);

            if ($stockNuevo < -0.0001) {
                throw ValidationException::withMessages([
                    'cantidad' => ['El ajuste deja el stock en negativo.'],
                ]);
            }

            if ($cantidad < 0) {
                $this->descontarLotes($producto, abs($cantidad), $validated['lote_id'] ?? null);
            } else {
                $this->incrementarLote($producto, $cantidad, $validated['fecha']);
            }

            $ajuste = AjusteInventario::query()->create([
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'motivo_id' => $motivo->id,
                'usuario_id' => $userId,
                'lote_id' => $validated['lote_id'] ?? null,
                'fecha' => $validated['fecha'],
                'observacion' => $validated['observacion'] ?? null,
            ]);

            $producto->update([
                'stock_actual' => $stockNuevo,
                'mod_user' => $userId,
            ]);

            InventarioMovimiento::query()->create([
                'producto_id' => $producto->id,
                'tipo' => 'ajuste_inventario',
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'costo_unitario' => $producto->costo_promedio,
                'referencia' => 'AJU-'.$ajuste->id,
                'nota' => $motivo->nombre.($ajuste->observacion ? ': '.$ajuste->observacion : ''),
                'add_user' => $userId,
            ]);

            return $ajuste;
        });

        return response()->json([
            'message' => 'Ajuste registrado correctamente.',
            'data' => $ajuste->load([
                'producto:id,nombre',
                'motivo:id,nombre,tipo',
                'usuario:id,name',
                'lote:id,fecha_vencimiento,fecha_entrada',
            ]),
        ], 201);
    }

    private function descontarLotes(Producto $producto, float $cantidad, ?int $loteId = null): void
    {
        $remaining = toMoney($cantidad, 4);

        $query = InventarioLote::query()
            ->where('producto_id', $producto->id)
            ->where('cantidad_disponible', '>', 0)
            ->lockForUpdate();

        if ($loteId) {
            $query->where('id', $loteId);
        } else {
            $query->orderByRaw('fecha_vencimiento IS NULL')
                ->orderBy('fecha_vencimiento')
                ->orderBy('fecha_entrada')
                ->orderBy('id');
        }

        $lotes = $query->get();

        foreach ($lotes as $lote) {
            if ($remaining <= 0) {
                break;
            }

            $disponible = (float) $lote->cantidad_disponible;
            if ($disponible <= 0) {
                continue;
            }

            $usar = min($remaining, $disponible);
            $lote->update([
                'cantidad_disponible' => toMoney($disponible - $usar, 4),
            ]);

            $remaining = toMoney($remaining - $usar, 4);
        }

        if ($remaining > 0.0001) {
            throw ValidationException::withMessages([
                'cantidad' => ['No hay lotes suficientes para realizar el ajuste de salida.'],
            ]);
        }
    }

    private function incrementarLote(Producto $producto, float $cantidad, string $fecha): void
    {
        if (! $producto->control_vencimiento) {
            return;
        }

        InventarioLote::query()->create([
            'producto_id' => $producto->id,
            'compra_detalle_id' => null,
            'cantidad_inicial' => $cantidad,
            'cantidad_disponible' => $cantidad,
            'costo_unitario' => toMoney((float) $producto->costo_promedio, 4),
            'fecha_vencimiento' => null,
            'fecha_entrada' => $fecha,
        ]);
    }
}
