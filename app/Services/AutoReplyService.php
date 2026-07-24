<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class AutoReplyService
{
    public const DEFAULT_BODY = 'Hola, recibimos tu mensaje. Un agente te atenderá en breve.';

    public function __construct(private readonly WhatsappService $whatsapp)
    {
    }

    public function handleInboundMessage(Conversation $conversation, Message $message): bool
    {
        $conversation = $conversation->fresh();

        if (! $this->shouldSend($conversation, $message)) {
            return false;
        }

        return DB::transaction(function () use ($conversation, $message): bool {
            $lockedConversation = $conversation->newQuery()
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedConversation) {
                return false;
            }

            $lockedConversation = $lockedConversation->fresh();

            if (! $this->shouldSend($lockedConversation, $message)) {
                return false;
            }

            $contact = $lockedConversation->contact()->first();

            if (! $contact?->wa_id) {
                return false;
            }

            // La llamada ocurre dentro del worker, no dentro del webhook. El
            // bloqueo evita que dos jobs envíen a la vez para el mismo chat.
            // Si Meta falla, la excepción revierte la transacción y la cola
            // vuelve a intentar sin dejar auto_reply_sent en true.
            $response = $this->whatsapp->sendText($contact->wa_id, self::DEFAULT_BODY);

            $waMessageId = (string) data_get($response, 'messages.0.id', '');

            if ($waMessageId === '') {
                throw new UnexpectedValueException('WhatsApp no devolvió el identificador del mensaje automático.');
            }

            if (Message::where('wa_message_id', $waMessageId)->exists()) {
                throw new UnexpectedValueException('WhatsApp devolvió un identificador de mensaje duplicado.');
            }

            $lockedConversation->messages()->create([
                'wa_message_id' => $waMessageId,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => self::DEFAULT_BODY,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $lockedConversation->update(['auto_reply_sent' => true]);

            return true;
        });
    }

    private function shouldSend(Conversation $conversation, Message $message): bool
    {
        if (! $message->isInbound()) {
            return false;
        }

        if ($message->sender_user_id !== null) {
            return false;
        }

        if ($message->direction !== 'inbound') {
            return false;
        }

        if ($conversation->assigned_user_id !== null) {
            return false;
        }

        if ($conversation->status === 'closed') {
            return false;
        }

        if ((bool) $conversation->auto_reply_sent) {
            return false;
        }

        if ($conversation->messages()->where('direction', 'outbound')->where('body', self::DEFAULT_BODY)->exists()) {
            return false;
        }

        if ($message->body === self::DEFAULT_BODY) {
            return false;
        }

        return true;
    }
}
