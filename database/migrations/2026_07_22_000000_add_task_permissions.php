<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $permissions = [
        'tareas.ver' => 'Ver tareas asignadas y su seguimiento',
        'tareas.crear' => 'Crear y asignar tareas a agentes de soporte',
        'tareas.completar' => 'Marcar como realizada una tarea propia',
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
        $roleIds = DB::table('roles')
            ->whereIn('name', ['administrador', 'soporte'])
            ->pluck('id', 'name');

        $assignments = [
            'administrador' => ['tareas.ver', 'tareas.crear'],
            'soporte' => ['tareas.ver', 'tareas.completar'],
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            if (! isset($roleIds[$roleName])) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                DB::table('permission_role')->updateOrInsert(
                    [
                        'permission_id' => $permissionIds[$permissionName],
                        'role_id' => $roleIds[$roleName],
                    ],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
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
