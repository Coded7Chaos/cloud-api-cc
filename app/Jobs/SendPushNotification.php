<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Saca el envío de notificaciones del camino crítico del webhook de WhatsApp.
 *
 * Antes se llamaba a APNs/FCM/Web Push en pleno request de Meta: cada
 * dispositivo del agente sumaba una llamada HTTP a un servicio externo, y si
 * tardaban, Meta daba el webhook por fallido y reintentaba el mismo evento.
 *
 * Se pasa el id y no el modelo para que el payload del job no arrastre el
 * usuario serializado entero.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly int $userId,
        private readonly string $title,
        private readonly string $body,
        private readonly array $data = [],
    ) {}

    public function handle(PushNotificationService $push): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        // Un agente puede tomar el chat antes de que el worker alcance el job
        // de "chat nuevo". En ese caso no se entrega un aviso que ya nació
        // vencido; la cancelación encolada por el ganador se ocupa de uno que
        // sí hubiera alcanzado a mostrarse.
        if (($this->data['event'] ?? null) === 'new_chat') {
            $conversationId = (int) ($this->data['conversation_id'] ?? 0);
            $stillUnclaimed = Conversation::query()
                ->whereKey($conversationId)
                ->whereNull('assigned_user_id')
                ->exists();

            if (! $stillUnclaimed) {
                return;
            }
        }

        $push->deliver($user, new PushMessage($this->title, $this->body, $this->data));
    }
}
