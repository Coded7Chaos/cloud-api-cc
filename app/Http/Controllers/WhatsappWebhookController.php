<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Contact;
use App\Models\Message;
use App\Services\ConversationNotificationService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly ConversationNotificationService $notifications,
    ) {}

    /**
     * Verificación del webhook (handshake inicial de Meta).
     *
     * Meta hace un GET con hub.mode / hub.verify_token / hub.challenge.
     * (PHP convierte los puntos de la query en guiones bajos: hub_mode, etc.)
     * Si el verify_token coincide con el nuestro, devolvemos el challenge tal cual.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Recepción de eventos: mensajes entrantes y cambios de estado.
     *
     * SIEMPRE respondemos 200 rápido: si devolvés error, Meta reintenta
     * el mismo evento una y otra vez. La idempotencia (chequeo por
     * wa_message_id antes de crear) hace que esos reintentos no dupliquen filas.
     */
    public function receive(Request $request): Response
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                $this->handleIncomingMessages($value);
                $this->handleStatusUpdates($value);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleIncomingMessages(array $value): void
    {
        $messages = $value['messages'] ?? [];

        if ($messages === []) {
            return;
        }

        // El contacto que escribió (nombre de perfil + wa_id).
        $contactData = $value['contacts'][0] ?? [];
        $waId = $contactData['wa_id'] ?? ($messages[0]['from'] ?? null);

        if (! $waId) {
            return;
        }

        $contact = Contact::updateOrCreate(
            ['wa_id' => $waId],
            ['profile_name' => data_get($contactData, 'profile.name')],
        );

        foreach ($messages as $payload) {
            $this->storeInboundMessage($contact, $payload);
        }
    }

    /**
     * Cada mensaje decide su conversación por separado (no una vez por lote):
     * si el contacto escribe con la ventana de 24h todavía abierta, sigue en
     * el mismo hilo; si ya pasaron más de 24h desde su último mensaje, es un
     * chat nuevo -- el anterior queda archivado tal cual estaba, sin que este
     * mensaje lo reabra ni lo mueva.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeInboundMessage(Contact $contact, array $payload): void
    {
        $waMessageId = $payload['id'] ?? null;

        if (! $waMessageId) {
            return;
        }

        // Reentrega de Meta del mismo evento: ya se procesó, no la tratamos
        // de nuevo (evita que una redelivery tardía "mueva" el mensaje a una
        // conversación distinta solo porque ya pasaron 24h desde entonces).
        if (Message::where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $conversation = $contact->conversations()
            ->where('status', '!=', 'closed')
            ->windowOpen()
            ->latest('last_message_at')
            ->first();

        if (! $conversation) {
            $conversation = $contact->conversations()->create(['status' => 'open']);
        }

        $type = $payload['type'] ?? 'text';

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $waMessageId,
            'direction' => 'inbound',
            'type' => $type,
            'body' => $this->extractBody($payload, $type),
            'status' => 'delivered',
            'sent_at' => isset($payload['timestamp'])
                ? now()->setTimestamp((int) $payload['timestamp'])
                : now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $this->notifications->notifyInbound($conversation, $message, now());

        // Si trae media y todavía no la descargamos, la bajamos al storage privado.
        $mediaId = data_get($payload, "{$type}.id");

        if ($mediaId && $message->media()->doesntExist()) {
            try {
                $attributes = $this->whatsapp->downloadMedia($mediaId);

                // El endpoint de media de Meta NO devuelve el nombre del
                // archivo: el real viaja en el payload del mensaje. Sin esto,
                // los documentos entrantes quedan sin nombre.
                $attributes['original_filename'] ??= data_get($payload, "{$type}.filename");

                $message->media()->create($attributes);
            } catch (\Throwable $e) {
                // No rompemos el webhook por un fallo de descarga: se loguea y sigue.
                Log::warning('WhatsApp: fallo al descargar media', [
                    'media_id' => $mediaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Tiempo real: avisa al chat abierto. Va al final para que el mensaje
        // ya tenga su media adjunta cuando se transmite.
        MessageCreated::dispatch($message);
    }

    /**
     * El texto vive en text.body; las media traen un caption opcional.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractBody(array $payload, string $type): ?string
    {
        return match ($type) {
            'text' => data_get($payload, 'text.body'),
            'button' => data_get($payload, 'button.text'),
            'interactive' => data_get($payload, 'interactive.list_reply.title')
                ?? data_get($payload, 'interactive.button_reply.title'),
            'image', 'video', 'document', 'audio' => data_get($payload, "{$type}.caption"),
            default => null,
        };
    }

    /**
     * Estados de los mensajes salientes: sent -> delivered -> read (o failed).
     * Llegan como eventos separados, referenciando el wamid del saliente.
     *
     * @param  array<string, mixed>  $value
     */
    private function handleStatusUpdates(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $waMessageId = $status['id'] ?? null;

            if (! $waMessageId) {
                continue;
            }

            Message::where('wa_message_id', $waMessageId)
                ->update(['status' => $status['status'] ?? null]);
        }
    }
}
