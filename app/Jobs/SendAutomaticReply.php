<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AutoReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía la respuesta automática fuera del request del webhook.
 *
 * El servicio vuelve a validar el estado de la conversación bajo bloqueo, por
 * lo que un job atrasado no responde si mientras tanto un agente tomó el chat.
 */
class SendAutomaticReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly int $conversationId,
        private readonly int $messageId,
    ) {}

    public function handle(AutoReplyService $autoReply): void
    {
        $conversation = Conversation::find($this->conversationId);
        $message = Message::find($this->messageId);

        if (! $conversation || ! $message || $message->conversation_id !== $conversation->id) {
            return;
        }

        $autoReply->handleInboundMessage($conversation, $message);
    }
}
