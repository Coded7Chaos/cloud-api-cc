<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Autenticación del panel, para sus dos clientes.
 *
 * - SPA web (mismo origen): sesión + cookie, igual que siempre. La cookie de
 *   sesión y el token XSRF nos dan el "SPA authentication" de Sanctum.
 * - Apps móviles: no hay cookie que valga, así que el login devuelve un token
 *   personal (Bearer) que la app guarda en el llavero del sistema.
 *
 * El modo se decide solo: si la petición viene de un dominio stateful trae
 * sesión y va por cookie; si no (el caso del móvil), se emite token.
 */
class AuthController extends Controller
{
    /** Inicia sesión: cookie para el SPA, token Bearer para el móvil. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        // Un cliente puede pedir token explícitamente (mandando device_name)
        // aunque tenga sesión disponible; es lo que haría una app de escritorio.
        if ($this->wantsToken($request)) {
            return $this->loginWithToken($request, $credentials);
        }

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        )) {
            throw $this->invalidCredentials();
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $request->user()->load('role.permissions'),
        ]);
    }

    /** Usuario autenticado actual (lo consultan el SPA y la app al arrancar). */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('role.permissions'),
        ]);
    }

    /**
     * Cierra sesión.
     *
     * En móvil revoca SOLO el token del dispositivo que la pidió (los demás
     * teléfonos del agente siguen andando) y de paso da de baja su registro de
     * push, para no seguir mandando notificaciones a un aparato deslogueado.
     * En web invalida la sesión, como antes.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        // Con sesión, currentAccessToken() devuelve un TransientToken que no se
        // borra; solo los tokens reales de la tabla se revocan.
        if ($token instanceof PersonalAccessToken) {
            $request->user()->deviceTokens()
                ->where('personal_access_token_id', $token->getKey())
                ->delete();

            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * Sin sesión disponible no hay cookie que sostenga el login, así que la
     * única salida es el token. Con device_name, el cliente lo pide a propósito.
     */
    private function wantsToken(Request $request): bool
    {
        return $request->filled('device_name') || ! $request->hasSession();
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function loginWithToken(Request $request, array $credentials): JsonResponse
    {
        // Solo email + password: retrieveByCredentials arma el WHERE con cada
        // clave que reciba, y un device_name suelto buscaría una columna que no
        // existe.
        $only = ['email' => $credentials['email'], 'password' => $credentials['password']];

        $provider = Auth::createUserProvider('users');
        $user = $provider->retrieveByCredentials($only);

        // validateCredentials devuelve false si el usuario todavía no estableció
        // su contraseña (invitación pendiente): password queda NULL en la tabla.
        if (! $user instanceof User || ! $provider->validateCredentials($user, $only)) {
            throw $this->invalidCredentials();
        }

        // Un token por dispositivo: volver a loguear en el mismo teléfono
        // reemplaza el anterior en vez de acumular tokens huérfanos.
        $deviceName = $credentials['device_name'] ?? 'movil';
        $user->tokens()->where('name', $deviceName)->delete();

        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user->load('role.permissions'),
        ]);
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => ['Las credenciales no coinciden con nuestros registros.'],
        ]);
    }
}
