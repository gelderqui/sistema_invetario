<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ManualController extends Controller
{
    public function usuario(): JsonResponse
    {
        $manualPath = base_path('readme/README_MANUAL_USUARIO.md');

        if (! is_file($manualPath)) {
            return response()->json([
                'message' => 'No se encontro el manual de usuario.',
                'data' => [
                    'titulo' => 'Manual de Usuario',
                    'markdown' => '',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'titulo' => 'Manual de Usuario',
                'markdown' => (string) file_get_contents($manualPath),
                'archivo' => 'readme/README_MANUAL_USUARIO.md',
            ],
        ]);
    }
}
