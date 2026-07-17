<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ABM de agentes (usuarios del panel).
 */
class UserController extends Controller
{
    /** Lista de usuarios para la pantalla "Usuarios". */
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('role:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']);

        return response()->json(['data' => $users]);
    }

    /** Crea un usuario. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        // password se castea a "hashed" en el modelo, así que se guarda cifrado.
        $data['email_verified_at'] = now();
        $user = User::create($data);

        return response()->json([
            'data' => $user->only(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']),
        ], 201);
    }

    /** Actualiza un usuario. La contraseña es opcional. */
    public function update(Request $request, User $user): JsonResponse
    {
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

        return response()->json([
            'data' => $user->only(['id', 'name', 'last_name', 'email', 'email_verified_at', 'created_at', 'role_id']),
        ]);
    }

    /** Elimina (soft delete) un usuario. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // No permitir que un usuario se borre a sí mismo desde el panel.
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
