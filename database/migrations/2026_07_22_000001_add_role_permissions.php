<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos para la gestión de roles desde el panel. Mismo patrón idempotente
 * que add_task_permissions: updateOrInsert para poder correrla sobre una base
 * que ya tiene datos sin duplicar filas. Solo el administrador los recibe.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $permissions = [
        'roles.ver' => 'Ver los roles y sus permisos',
        'roles.crear' => 'Crear roles',
        'roles.editar' => 'Editar roles y asignar permisos',
        'roles.eliminar' => 'Eliminar roles',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['description' => $description, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($this->permissions))
            ->pluck('id', 'name');
        $adminRoleId = DB::table('roles')->where('name', 'administrador')->value('id');

        if ($adminRoleId === null) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $adminRoleId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($this->permissions))
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
