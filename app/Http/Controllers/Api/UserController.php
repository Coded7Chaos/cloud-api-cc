<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Services\AuditLogService;
use App\Services\UserDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ABM de agentes (usuarios del panel).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly UserDeletionService $userDeletion,
    ) {}

    /** Lista de usuarios para la pantalla "Usuarios". */
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('role:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']);

        return response()->json(['data' => $users]);
    }

    /** Crea un usuario y le envía una invitación para que fije su contraseña. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        // La contraseña la fija el propio agente desde el enlace de invitación,
        // así que se crea sin contraseña.
        $user = User::create([
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => null,
            'role_id' => $data['role_id'],
        ]);

        $token = PasswordBroker::createToken($user);
        $user->notify(new UserInvitationNotification($token));
        $this->audit->record(
            'usuarios',
            'usuario_creado',
            "Creó el usuario {$user->email} y envió una invitación.",
            $request->user(),
            $user,
            ['role' => Role::whereKey($data['role_id'])->value('name')],
        );

        return response()->json([
            'data' => $user->only(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']),
            'message' => 'Usuario creado. Enviamos una invitación para establecer su contraseña.',
        ], 201);
    }

    /** Actualiza un usuario. La contraseña es opcional. */
    public function update(Request $request, User $user): JsonResponse
    {
        $before = $user->only(['name', 'last_name', 'email', 'role_id']);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $changes = collect($user->getChanges())->except(['updated_at', 'password', 'remember_token'])->keys()->values()->all();

        if ($changes !== []) {
            $this->audit->record(
                'usuarios',
                'usuario_editado',
                "Editó el usuario {$user->email}.",
                $request->user(),
                $user,
                ['fields' => $changes, 'before' => $before, 'after' => $user->only(['name', 'last_name', 'email', 'role_id'])],
            );
        }

        if (array_key_exists('password', $data)) {
            $this->audit->record(
                'usuarios',
                'contrasena_cambiada',
                "Cambió la contraseña de {$user->email}.",
                $request->user(),
                $user,
            );
        }

        return response()->json([
            'data' => $user->only(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']),
        ]);
    }

    /** Elimina definitivamente los datos del usuario, conservando su nombre en auditoría. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // No permitir que un usuario se borre a sí mismo desde el panel.
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        $this->userDeletion->delete($user, $request->user());

        return response()->json(null, 204);
    }
}
