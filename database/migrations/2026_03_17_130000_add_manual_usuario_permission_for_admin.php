<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionCode = 'manual_usuario';

        $permissionId = DB::table('permissions')->where('code', $permissionCode)->value('id');

        if (! $permissionId) {
            DB::table('permissions')->insert([
                'name' => 'Manual de usuario',
                'code' => $permissionCode,
                'module' => 'configuracion',
                'module_label' => 'Configuracion',
                'module_icono' => 'fa-solid fa-gears',
                'ruta' => '/manual/usuario',
                'icono' => 'fa-solid fa-book-open-reader',
                'orden' => 83,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permissionId = DB::table('permissions')->where('code', $permissionCode)->value('id');
        }

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');

        if ($adminRoleId && $permissionId) {
            $exists = DB::table('permission_role')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'manual_usuario')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
