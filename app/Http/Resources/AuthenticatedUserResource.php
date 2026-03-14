<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['role.permissions']);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'code' => $this->role->code,
                'description' => $this->role->description,
            ] : null,
            'permissions' => $this->allPermissions()->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'code' => $permission->code,
                'module' => $permission->module,
                'ruta' => $permission->ruta,
                'icono' => $permission->icono,
                'orden' => $permission->orden,
            ])->values(),
        ];
    }
}