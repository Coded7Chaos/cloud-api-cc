<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->where('name', 'auditoria.ver')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'auditoria.ver',
                'description' => 'Ver registros de auditoría del panel',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'administrador')->value('id');

        if ($adminRoleId) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $adminRoleId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'auditoria.ver')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'administrador')->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', $adminRoleId)
                ->delete();
        }
    }
};
