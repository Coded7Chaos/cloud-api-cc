<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones nativas de iOS, hablando directo con APNs.
 *
 * Sin Firebase de por medio: se firma un JWT ES256 con la clave .p8 del
 * Apple Developer Program y se hace POST por HTTP/2 a api.push.apple.com.
 * No cuesta nada más que la cuenta de desarrollador que igual hace falta
 * para publicar la app.
 */
class ApnsChannel implements PushChannel
{
    /** Apple acepta el token hasta 60 min; se renueva antes para no rozar el borde. */
    private const TOKEN_TTL_MINUTES = 50;

    public function name(): string
    {
        return 'apns';
    }

    public function isConfigured(): bool
    {
        return (bool) (config('push.apns.key_id')
            && config('push.apns.team_id')
            && config('push.apns.bundle_id')
            && $this->privateKey());
    }

    public function send(User $user, PushMessage $message): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $devices = $user->deviceTokens()->platform(DeviceToken::PLATFORM_IOS)->get();

        foreach ($devices as $device) {
            try {
                $this->deliver($device, $message);
            } catch (\Throwable $e) {
                Log::warning('APNs: fallo al enviar notificación', [
                    'device_token_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function deliver(DeviceToken $device, PushMessage $message): void
    {
        $response = Http::withHeaders([
            'authorization' => 'bearer '.$this->authToken(),
            'apns-topic' => (string) config('push.apns.bundle_id'),
            'apns-push-type' => 'alert',
            // 10 = entregar ya. Es un chat de atención al cliente: llegar tarde
            // es casi lo mismo que no llegar.
            'apns-priority' => '10',
            'apns-expiration' => (string) now()->addHour()->timestamp,
        ])
            ->withOptions($this->http2Options())
            ->withBody($this->payload($message), 'application/json')
            ->post($this->endpoint().'/3/device/'.$device->token);

        $this->handleFailure($response, $device);
    }

    /**
     * Apple avisa que un token murió con 410 (Unregistered) o con 400 +
     * BadDeviceToken: en los dos casos el aparato ya no existe para nosotros y
     * seguir intentándolo solo gasta cuota.
     */
    private function handleFailure(Response $response, DeviceToken $device): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = (string) $response->json('reason', '');

        if ($response->status() === 410 || in_array($reason, ['Unregistered', 'BadDeviceToken'], true)) {
            $device->delete();

            return;
        }

        Log::warning('APNs: rechazo al enviar notificación', [
            'device_token_id' => $device->id,
            'status' => $response->status(),
            'reason' => $reason,
        ]);
    }

    private function payload(PushMessage $message): string
    {
        return (string) json_encode([
            'aps' => [
                'alert' => [
                    'title' => $message->title,
                    'body' => $message->body,
                ],
                'sound' => 'default',
                // Deja que la app actualice el badge/estado al recibirla.
                'mutable-content' => 1,
            ],
        ] + $message->stringData(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * El JWT vale para todos los envíos, así que se cachea: Apple penaliza
     * (429 TooManyProviderTokenUpdates) si se regenera en cada notificación.
     */
    private function authToken(): string
    {
        return Cache::remember('push:apns:jwt', now()->addMinutes(self::TOKEN_TTL_MINUTES), fn () => Jwt::sign(
            ['alg' => 'ES256', 'kid' => (string) config('push.apns.key_id'), 'typ' => 'JWT'],
            ['iss' => (string) config('push.apns.team_id'), 'iat' => now()->timestamp],
            (string) $this->privateKey(),
            'ES256',
        ));
    }

    /**
     * La clave .p8 puede venir por ruta de archivo o pegada en el .env (útil en
     * contenedores donde no querés montar archivos con secretos).
     */
    private function privateKey(): ?string
    {
        $inline = config('push.apns.private_key');

        if (is_string($inline) && trim($inline) !== '') {
            return str_replace('\n', "\n", $inline);
        }

        $path = config('push.apns.private_key_path');

        if (is_string($path) && $path !== '' && is_readable($path)) {
            return (string) file_get_contents($path);
        }

        return null;
    }

    private function endpoint(): string
    {
        return config('push.apns.production')
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';
    }

    /**
     * APNs solo habla HTTP/2. cURL lo negocia solo cuando está compilado con
     * nghttp2; si la constante no existe, no forzamos nada y que negocie él.
     *
     * @return array<string, mixed>
     */
    private function http2Options(): array
    {
        if (! defined('CURL_HTTP_VERSION_2_0')) {
            return [];
        }

        return ['curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0]];
    }
}
