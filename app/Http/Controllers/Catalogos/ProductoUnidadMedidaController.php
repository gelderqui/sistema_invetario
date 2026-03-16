<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\ProductoUnidadMedida;
use Illuminate\Http\JsonResponse;

class ProductoUnidadMedidaController extends Controller
{
    public function index(): JsonResponse
    {
        $medidas = ProductoUnidadMedida::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'abreviatura']);

        return response()->json([
            'data' => $medidas,
        ]);
    }
}
