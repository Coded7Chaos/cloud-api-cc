<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /** Un permiso por cada acción existente en los controladores del panel. */
    private const PERMISSIONS = [
        'usuarios.ver' => 'Ver la lista de usuarios del panel',
        'usuarios.crear' => 'Crear usuarios del panel',
        'usuarios.editar' => 'Editar usuarios del panel',
        'usuarios.eliminar' => 'Eliminar (soft delete) usuarios del panel',
        'conversaciones.ver' => 'Ver la bandeja de chats y sus mensajes',
        'conversaciones.responder' => 'Enviar mensajes salientes en un chat',
        'horarios.ver' => 'Ver los horarios de los agentes',
        'horarios.editar' => 'Editar los turnos de horario de un agente',
    ];

    private const ROLE_PERMISSIONS = [
        'administrador' => [
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
            'conversaciones.ver', 'conversaciones.responder',
            'horarios.ver', 'horarios.editar',
        ],
        'soporte' => ['conversaciones.ver', 'conversaciones.responder', 'horarios.ver'],
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(
            fn (string $description, string $name) => Permission::firstOrCreate(
                ['name' => $name],
                ['description' => $description],
            ),
        );

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->permissions()->sync($permissions->only($permissionNames)->pluck('id'));
        }
    }
}
