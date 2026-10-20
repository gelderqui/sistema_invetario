<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultaPreciosController extends Controller
{
    /**
     * Consulta de solo lectura para personal que atiende precios sin operar POS.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buscar' => ['nullable', 'string', 'max:255'],
        ]);

        $buscar = trim((string) ($validated['buscar'] ?? ''));

        if ($buscar === '') {
            return response()->json(['data' => []]);
        }

        $productos = Producto::query()
            ->with('unidadMedida:id,nombre,abreviatura')
            ->where('activo', true)
            ->where(function ($query) use ($buscar): void {
                $like = '%'.$buscar.'%';

                $query->where('nombre', 'like', $like)
                    ->orWhere('codigo_barra', 'like', $like)
                    ->orWhere('palabras_clave', 'like', $like);
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get([
                'id',
                'nombre',
                'codigo_barra',
                'precio_venta',
                'stock_actual',
                'unidad_medida_id',
            ]);

        return response()->json([
            'data' => $productos,
        ]);
    }
}
