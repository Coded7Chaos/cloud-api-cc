<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'message' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.',
        ]);
    }
}
