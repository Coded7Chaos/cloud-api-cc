<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión de roles y sus permisos (pestaña "Roles" dentro de Usuarios).
 *
 * El catálogo lo sigue consumiendo el selector de rol del alta de usuarios: por
 * eso index devuelve id y name además de lo que pide la pantalla de gestión.
 */
class RoleController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** Roles con su cantidad de usuarios y los permisos que tienen asignados. */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => $this->transform($role));

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create(['name' => $data['name']]);
        $role->permissions()->sync($data['permissions'] ?? []);

        $this->audit->record(
            'usuarios',
            'rol_creado',
            "Creó el rol \"{$role->name}\".",
            $request->user(),
            null,
            ['role' => $role->name, 'permissions' => $role->permissions()->count()],
        );

        return response()->json(['data' => $this->transform($role)], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validateRole($request, $role);

        // Los roles del sistema conservan su nombre (hay lógica que depende de
        // él); sus permisos sí se pueden reasignar.
        if ($role->isProtected() && isset($data['name']) && $data['name'] !== $role->name) {
            return response()->json([
                'message' => "El rol \"{$role->name}\" es del sistema y no se puede renombrar.",
            ], 422);
        }

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        $this->audit->record(
            'usuarios',
            'rol_editado',
            "Editó el rol \"{$role->name}\".",
            $request->user(),
            null,
            ['role' => $role->name],
        );

        return response()->json(['data' => $this->transform($role->fresh())]);
    }

    /**
     * Borra el rol. Los usuarios que lo tenían quedan sin rol (role_id = null):
     * la FK es restrictOnDelete, así que hay que soltarlos a mano antes, o la
     * base rechaza el DELETE. Se avisa a cuántos quedaron sin rol en la respuesta.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->isProtected()) {
            return response()->json([
                'message' => "El rol \"{$role->name}\" es del sistema y no se puede eliminar.",
            ], 422);
        }

        // Sin esto un administrador podría sacarse el piso a sí mismo y quedar
        // sin acceso a esta misma pantalla.
        if ($request->user()->role_id === $role->id) {
            return response()->json([
                'message' => 'No puedes eliminar el rol que tú mismo tienes asignado.',
            ], 422);
        }

        $affected = $role->users()->count();
        $role->users()->update(['role_id' => null]);
        $role->delete();

        $this->audit->record(
            'usuarios',
            'rol_eliminado',
            "Eliminó el rol \"{$role->name}\".",
            $request->user(),
            null,
            ['role' => $role->name, 'users_left_without_role' => $affected],
        );

        return response()->json(['data' => ['users_left_without_role' => $affected]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            // En alta el nombre es obligatorio; en edición es opcional, para
            // poder guardar solo los permisos sin reenviar el nombre.
            'name' => [
                $role ? 'sometimes' : 'required',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Role $role): array
    {
        $role->loadCount('users')->load('permissions:id,name');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'is_protected' => $role->isProtected(),
            'users_count' => $role->users_count,
            'permissions' => $role->permissions->pluck('id')->values(),
        ];
    }
}
