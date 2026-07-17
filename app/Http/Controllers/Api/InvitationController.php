<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['status' => 'invalid'], 404);
        }

        if ($user->password !== null) {
            return response()->json(['status' => 'already_set']);
        }

        if (! PasswordBroker::tokenExists($user, $data['token'])) {
            return response()->json(['status' => 'invalid'], 422);
        }

        return response()->json([
            'status' => 'pending',
            'email' => $user->email,
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['El enlace de invitación no es válido.'],
            ]);
        }

        if ($user->password !== null) {
            return response()->json([
                'status' => 'already_set',
                'message' => 'Ya se estableció la contraseña para esta cuenta.',
            ]);
        }

        if (! PasswordBroker::tokenExists($user, $data['token'])) {
            throw ValidationException::withMessages([
                'email' => ['El enlace de invitación no es válido o ya venció.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        PasswordBroker::deleteToken($user);
        $this->audit->record(
            'usuarios',
            'contrasena_inicial_creada',
            "El usuario {$user->email} estableció su contraseña inicial.",
            null,
            $user,
        );

        return response()->json([
            'status' => 'created',
            'message' => 'Tu contraseña fue creada. Ya puedes iniciar sesión.',
        ]);
    }
}
