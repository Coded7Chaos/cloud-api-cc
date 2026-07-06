<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Envío de mensajes salientes desde el panel.
 *
 * Persiste el mensaje Y lo entrega a la WhatsApp Cloud API. El wa_message_id
 * pasa a ser el wamid real que devuelve Meta (clave para luego cruzar los
 * webhooks de estado: sent -> delivered -> read).
 */
class MessageController extends Controller
{
    public function __construct(private readonly WhatsappService $whatsapp) {}

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $conversation->loadMissing('contact');

        // 1) Guardamos primero como "pending" para no perder el mensaje aunque
        //    el envío a Meta falle. wa_message_id provisional hasta tener el wamid.
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'local-'.Str::uuid(),
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $data['body'],
            'status' => 'pending',
            'sender_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        // 2) Entrega real a la Cloud API.
        try {
            $response = $this->whatsapp->sendText($conversation->contact->wa_id, $data['body']);

            $message->update([
                'wa_message_id' => data_get($response, 'messages.0.id', $message->wa_message_id),
                'status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            // No reventamos el panel: el mensaje queda como "failed" y el agente lo ve.
            $message->update(['status' => 'failed']);

            Log::warning('WhatsApp: fallo al enviar mensaje', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        $conversation->update(['last_message_at' => $message->sent_at]);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'status' => $message->fresh()->status,
                'sent_at' => $message->sent_at,
                'created_at' => $message->created_at,
                'sender' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            ],
        ], 201);
    }
}
