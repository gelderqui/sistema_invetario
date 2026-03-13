<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('activo', true)
            ->orderBy('module')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'module',
            ]);

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
