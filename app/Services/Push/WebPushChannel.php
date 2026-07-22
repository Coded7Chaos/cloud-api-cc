<?php

namespace App\Services\Push;

use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Notificaciones del navegador (Web Push / VAPID), para el panel web.
 *
 * Es el canal que ya existía; acá solo pasó a implementar PushChannel para
 * convivir con los nativos detrás de la misma fachada.
 */
class WebPushChannel implements PushChannel
{
    public function name(): string
    {
        return 'webpush';
    }

    public function isConfigured(): bool
    {
        return (bool) (config('services.webpush.vapid_public_key')
            && config('services.webpush.vapid_private_key'));
    }

    public function send(User $user, PushMessage $message): void
    {
        if (! $this->isConfigured()) {
            Log::info('WebPush: VAPID no configurado, no se envió push.');

            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush($this->auth());
        $payload = json_encode([
            'title' => $message->title,
            'body' => $message->body,
            'url' => $message->url(),
            'data' => $message->data,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $stored) {
            try {
                $report = $webPush->sendOneNotification($this->subscription($stored), $payload ?: null);

                if ($report->isSubscriptionExpired()) {
                    $stored->delete();
                } elseif (! $report->isSuccess()) {
                    Log::warning('WebPush: fallo al enviar notificación', [
                        'endpoint' => $stored->endpoint,
                        'reason' => $report->getReason(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('WebPush: error inesperado al enviar notificación', [
                    'endpoint' => $stored->endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function auth(): array
    {
        return [
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => (string) config('services.webpush.vapid_public_key'),
                'privateKey' => (string) config('services.webpush.vapid_private_key'),
            ],
        ];
    }

    private function subscription(StoredPushSubscription $stored): Subscription
    {
        return Subscription::create([
            'endpoint' => $stored->endpoint,
            'publicKey' => $stored->public_key,
            'authToken' => $stored->auth_token,
            'contentEncoding' => $stored->content_encoding,
        ]);
    }
}
