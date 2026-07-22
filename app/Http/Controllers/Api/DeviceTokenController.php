<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registro de dispositivos móviles para notificaciones nativas.
 *
 * Es el equivalente de PushSubscriptionController pero del lado de las apps:
 * el teléfono pide su token al sistema (APNs en iOS, FCM en Android) y lo
 * deja acá para que el backend pueda despertarlo cuando entra un mensaje.
 */
class DeviceTokenController extends Controller
{
    /** Dispositivos del agente, para que la app pueda mostrarlos o depurar. */
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->deviceTokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (DeviceToken $device) => [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'app_version' => $device->app_version,
                'last_used_at' => $device->last_used_at,
            ]);

        return response()->json(['data' => $devices]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::in([DeviceToken::PLATFORM_IOS, DeviceToken::PLATFORM_ANDROID])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $accessToken = $request->user()->currentAccessToken();

        // updateOrCreate sobre el hash: si el teléfono vuelve a registrar el
        // mismo token (arranque de la app, rotación, cambio de usuario en el
        // mismo aparato) se actualiza la fila en vez de duplicarla, y el
        // dispositivo queda atado al usuario que lo registró último.
        $device = DeviceToken::updateOrCreate(
            ['token_hash' => DeviceToken::hashFor($data['token'])],
            [
                'user_id' => $request->user()->getKey(),
                'personal_access_token_id' => $accessToken instanceof PersonalAccessToken
                    ? $accessToken->getKey()
                    : null,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'data' => ['id' => $device->id, 'platform' => $device->platform],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $request->user()->deviceTokens()
            ->where('token_hash', DeviceToken::hashFor($data['token']))
            ->delete();

        return response()->json(null, 204);
    }
}
