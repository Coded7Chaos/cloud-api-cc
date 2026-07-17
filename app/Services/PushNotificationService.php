<?php

namespace App\Services;

use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $auth = $this->auth();

        if (! $auth) {
            Log::info('WebPush: VAPID no configurado, no se envió push.');

            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $data['url'] ?? '/chats',
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $stored) {
            $report = $webPush->sendOneNotification($this->subscription($stored), $payload ?: null);

            if ($report->isSubscriptionExpired()) {
                $stored->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('WebPush: fallo al enviar notificación', [
                    'endpoint' => $stored->endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private function auth(): ?array
    {
        $publicKey = config('services.webpush.vapid_public_key');
        $privateKey = config('services.webpush.vapid_private_key');

        if (! $publicKey || ! $privateKey) {
            return null;
        }

        return [
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
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
