<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $user = $request->user();
        $conversation->authorizeAndClaimFor($user);

        if (! $conversation->canSendFreeform()) {
            return response()->json([
                'message' => 'Pasaron más de 24 horas desde el último mensaje del cliente. No se pueden enviar mensajes hasta que vuelva a escribir.',
            ], 422);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1024'],
            'media' => ['nullable', 'file', 'max:16384', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/3gpp'],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('media')) {
            throw ValidationException::withMessages([
                'body' => ['Escribe un mensaje o adjunta una imagen/video.'],
            ]);
        }

        $conversation->loadMissing('contact');
        $file = $request->file('media');
        $type = $file ? Str::of((string) $file->getMimeType())->before('/')->value() : 'text';
        $body = trim((string) ($data['body'] ?? ''));

        // 1) Guardamos primero como "pending" para no perder el mensaje aunque
        //    el envío a Meta falle. wa_message_id provisional hasta tener el wamid.
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'local-'.Str::uuid(),
            'direction' => 'outbound',
            'type' => $type,
            'body' => $body !== '' ? $body : null,
            'status' => 'pending',
            'sender_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        if ($file) {
            $path = $file->store('whatsapp/outbound', 'local');
            $message->media()->create([
                'disk' => 'local',
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'original_filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            ]);
        }

        // 2) Entrega real a la Cloud API.
        try {
            if ($file) {
                $upload = $this->whatsapp->uploadMedia($file);
                $mediaId = (string) data_get($upload, 'id');
                $response = $this->whatsapp->sendMedia($conversation->contact->wa_id, $type, $mediaId, $message->body);
                $message->media()->update(['wa_media_id' => $mediaId]);
            } else {
                $response = $this->whatsapp->sendText($conversation->contact->wa_id, $body);
            }

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
        $message->load('media');

        return response()->json([
            'data' => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'body' => $message->body,
                'status' => $message->fresh()->status,
                'sent_at' => $message->sent_at,
                'created_at' => $message->created_at,
                'media' => $message->media->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->url(),
                    'mime_type' => $media->mime_type,
                    'original_filename' => $media->original_filename,
                    'size' => $media->size,
                ])->values(),
                'sender' => ['id' => $user->id, 'name' => $user->name],
            ],
        ], 201);
    }
}
