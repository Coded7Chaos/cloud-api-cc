<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\User;
use App\Services\Push\PushChannel;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Log;

/**
 * Fachada de notificaciones: reparte un mismo aviso a todos los canales
 * habilitados (navegador, iOS, Android) sin que quien lo dispara sepa por
 * dónde va a salir.
 *
 * Los canales se resuelven desde config/push.php; los que no tengan
 * credenciales se saltean en silencio, así el entorno de desarrollo funciona
 * sin configurar nada.
 */
class PushNotificationService
{
    /** @var list<PushChannel> */
    private array $channels;

    /**
     * @param  list<PushChannel>|null  $channels
     */
    public function __construct(?array $channels = null)
    {
        $this->channels = $channels ?? array_map(
            fn (string $channel) => app($channel),
            (array) config('push.channels', []),
        );
    }

    /**
     * Encola el envío. Se llama desde el webhook de WhatsApp, que tiene que
     * responder 200 rápido: si tarda, Meta reintenta el mismo evento.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        SendPushNotification::dispatch($user->getKey(), $title, $body, $data);
    }

    /**
     * Envío real, ya dentro del worker. Cada canal se aísla: que APNs esté
     * caído no puede dejar sin notificación a los agentes en Android.
     */
    public function deliver(User $user, PushMessage $message): void
    {
        foreach ($this->channels as $channel) {
            if (! $channel->isConfigured()) {
                continue;
            }

            try {
                $channel->send($user, $message);
            } catch (\Throwable $e) {
                Log::warning('Push: canal falló al enviar', [
                    'channel' => $channel->name(),
                    'user_id' => $user->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
