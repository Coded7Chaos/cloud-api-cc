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

/**
 * Recuperación de contraseña del panel ("olvidé mi contraseña").
 *
 * Usa el password broker nativo de Laravel (tabla password_reset_tokens),
 * con el link de la notificación apuntando al SPA en vez de a una vista Blade.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** Pide el link de recuperación por correo. */
    public function sendResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // El broker solo manda el correo si el email existe en la tabla users;
        // si no existe, no hace nada. Respondemos siempre el mismo mensaje
        // genérico para no revelar qué correos están registrados.
        PasswordBroker::sendResetLink(['email' => $data['email']]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            $this->audit->record(
                'usuarios',
                'recuperacion_solicitada',
                "Solicitó recuperación de contraseña para {$user->email}.",
                null,
                $user,
            );
        }

        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.',
        ]);
    }

    /** Confirma el link (token + email) y setea la nueva contraseña. */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $status = PasswordBroker::reset(
            $data,
            function ($user, string $password) {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $user = User::query()->where('email', $data['email'])->first();
        if ($user) {
            $this->audit->record(
                'usuarios',
                'contrasena_recuperada',
                "Restableció la contraseña de {$user->email} mediante enlace de recuperación.",
                null,
                $user,
            );
        }

        return response()->json([
            'message' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.',
        ]);
    }
}
