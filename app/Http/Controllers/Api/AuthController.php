<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación del panel (SPA de mismo origen).
 *
 * Usa el guard "web" por sesión/cookie. El SPA vive en el mismo dominio que
 * Laravel, así que la cookie de sesión + el token XSRF nos dan el equivalente
 * al "SPA authentication" de Sanctum sin instalar el paquete.
 */
class AuthController extends Controller
{
    /** Inicia sesión y regenera la sesión para prevenir fixation. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /** Usuario autenticado actual (lo consulta el SPA al cargar). */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /** Cierra sesión e invalida la sesión + token. */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
