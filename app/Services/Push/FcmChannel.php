<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Notificaciones nativas de Android, hablando directo con FCM HTTP v1.
 *
 * Sin el SDK de Firebase: se firma un JWT RS256 con la service account, se lo
 * canjea por un access token OAuth2 y se hace POST a messages:send. El envío
 * de mensajes de FCM no tiene costo ni cuota facturable; lo que se paga en
 * Firebase son otros productos (Firestore, Storage, Functions).
 *
 * En Android no hay alternativa realista: el modo Doze mata los sockets
 * propios en segundo plano, así que despertar la app pasa sí o sí por FCM.
 */
class FcmChannel implements PushChannel
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const OAUTH_URL = 'https://oauth2.googleapis.com/token';

    public function name(): string
    {
        return 'fcm';
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return isset($credentials['client_email'], $credentials['private_key'])
            && $this->projectId() !== null;
    }

    public function send(User $user, PushMessage $message): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $devices = $user->deviceTokens()->platform(DeviceToken::PLATFORM_ANDROID)->get();

        foreach ($devices as $device) {
            try {
                $this->deliver($device, $message);
            } catch (\Throwable $e) {
                Log::warning('FCM: fallo al enviar notificación', [
                    'device_token_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function deliver(DeviceToken $device, PushMessage $message): void
    {
        // Mensaje sólo de datos: Flutter lo recibe también en background y
        // crea una notificación local con un id conocido. Ese mismo id permite
        // retirarla cuando otro agente toma el chat, algo imposible con el id
        // opaco de una notificación dibujada automáticamente por FCM.
        $data = [
            'title' => $message->title,
            'body' => $message->body,
            ...$message->stringData(),
        ];
        $conversationId = $data['conversation_id'] ?? null;

        $response = Http::withToken($this->accessToken())
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", [
                'message' => [
                    'token' => $device->token,
                    'data' => $data,
                    'android' => [
                        // "high" despierta la app aunque el teléfono esté en Doze.
                        'priority' => 'high',
                        // Si el aparato está sin conexión y mientras tanto otro
                        // agente toma el chat, FCM conserva sólo el último evento
                        // de esta conversación (la cancelación).
                        'collapse_key' => $conversationId ? "conversation_{$conversationId}" : 'cloud_api_cc',
                        'ttl' => '3600s',
                    ],
                ],
            ]);

        $this->handleFailure($response, $device);
    }

    /**
     * UNREGISTERED (app desinstalada) e INVALID_ARGUMENT sobre el token
     * significan que ese aparato ya no existe: se borra para no reintentar
     * eternamente contra un token muerto.
     */
    private function handleFailure(Response $response, DeviceToken $device): void
    {
        if ($response->successful()) {
            return;
        }

        $status = (string) $response->json('error.status', '');

        if ($response->status() === 404 || $status === 'UNREGISTERED' || $status === 'NOT_FOUND') {
            $device->delete();

            return;
        }

        Log::warning('FCM: rechazo al enviar notificación', [
            'device_token_id' => $device->id,
            'status' => $response->status(),
            'error' => $response->json('error.message'),
        ]);
    }

    /**
     * Access token OAuth2 a partir de la service account. Google los emite por
     * una hora; se cachea 55 min para no pedir uno por notificación.
     */
    private function accessToken(): string
    {
        return Cache::remember('push:fcm:access_token', now()->addMinutes(55), function (): string {
            $credentials = $this->credentials();
            $now = now()->timestamp;

            $assertion = Jwt::sign(
                ['alg' => 'RS256', 'typ' => 'JWT'],
                [
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $credentials['token_uri'] ?? self::OAUTH_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ],
                (string) $credentials['private_key'],
                'RS256',
            );

            $response = Http::asForm()->post($credentials['token_uri'] ?? self::OAUTH_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('FCM: no se pudo obtener el access token de la service account.');
            }

            return $token;
        });
    }

    private function projectId(): ?string
    {
        $configured = config('push.fcm.project_id');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fromCredentials = $this->credentials()['project_id'] ?? null;

        return is_string($fromCredentials) && $fromCredentials !== '' ? $fromCredentials : null;
    }

    /**
     * El JSON de la service account, por ruta de archivo o pegado en el .env.
     *
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $inline = config('push.fcm.credentials');

        if (is_string($inline) && trim($inline) !== '') {
            return json_decode($inline, true) ?: [];
        }

        $path = config('push.fcm.credentials_path');

        if (is_string($path) && $path !== '' && is_readable($path)) {
            return json_decode((string) file_get_contents($path), true) ?: [];
        }

        return [];
    }
}
