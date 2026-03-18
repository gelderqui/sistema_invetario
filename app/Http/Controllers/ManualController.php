<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    public function usuario(Request $request): JsonResponse
    {
        $user = $request->user()?->loadMissing('role:id,code');
        $isAdmin = $user?->role?->code === 'admin';

        $manualPath = $isAdmin
            ? base_path('readme/README_MANUAL_USUARIO.md')
            : base_path('readme/README_MANUAL_OPERATIVO.md');

        $title = $isAdmin ? 'Manual de Usuario' : 'Manual Operativo';

        if (! is_file($manualPath)) {
            return response()->json([
                'message' => 'No se encontro el manual de usuario.',
                'data' => [
                    'titulo' => $title,
                    'markdown' => '',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'titulo' => $title,
                'markdown' => (string) file_get_contents($manualPath),
                'archivo' => $isAdmin ? 'readme/README_MANUAL_USUARIO.md' : 'readme/README_MANUAL_OPERATIVO.md',
            ],
        ]);
    }
}
