<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    /**
     * Historial hacia atrás, para el scroll infinito del móvil.
     *
     * Cursor por id descendente en vez de ?page=N: el hilo crece por abajo
     * mientras el agente scrollea hacia arriba, y con offsets fijos cada
     * mensaje nuevo corre la ventana y hace que se repitan o se salteen filas.
     * Anclando en un id eso no pasa.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation->authorizeAndClaimFor($request->user());

        $limit = max(1, min((int) $request->integer('limit', 50), 200));

        $messages = $conversation->messages()
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', $request->integer('before')))
            ->with(['sender:id,name,last_name', 'media'])
            ->orderByDesc('id')
            ->limit($limit + 1) // uno de más: sirve para saber si quedan más atrás
            ->get();

        $hasMore = $messages->count() > $limit;
        $page = $messages->take($limit)->reverse()->values();

        return response()->json([
            'data' => $page->map(fn (Message $m) => $m->toApiArray())->values(),
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $page->isNotEmpty() ? $page->first()->id : null,
            ],
        ]);
    }

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
            // El tope real depende del tipo (ver abajo); acá solo se corta lo
            // que ni siquiera podría ser un documento.
            'media' => ['nullable', 'file', 'max:'.WhatsappService::maxKilobytesFor('document')],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('media')) {
            throw ValidationException::withMessages([
                'body' => ['Escribe un mensaje o adjunta un archivo.'],
            ]);
        }

        $conversation->loadMissing('contact');
        $file = $request->file('media');
        // El nombre desempata los contenedores ambiguos (un .m4a se detecta
        // como video/mp4): sin él, una nota de voz saldría como video.
        $type = $file
            ? WhatsappService::typeForMime($file->getMimeType(), $file->getClientOriginalName())
            : 'text';
        $mime = $file
            ? WhatsappService::normalizedMime($file->getMimeType(), $file->getClientOriginalName())
            : null;
        $body = trim((string) ($data['body'] ?? ''));

        if ($file) {
            $this->assertWithinTypeLimit($file, $type);

            // La Cloud API descarta el caption de los audios: si lo dejáramos
            // pasar, el agente vería su texto en el panel y el cliente no.
            if ($type === 'audio' && $body !== '') {
                throw ValidationException::withMessages([
                    'body' => ['Los audios no admiten texto. Envía el audio y el texto por separado.'],
                ]);
            }
        }

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
                'mime_type' => $mime,
                'original_filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            ]);
        }

        // 2) Entrega real a la Cloud API.
        try {
            if ($file) {
                $upload = $this->whatsapp->uploadMedia($file, $mime);
                $mediaId = (string) data_get($upload, 'id');
                $response = $this->whatsapp->sendMedia(
                    $conversation->contact->wa_id,
                    $type,
                    $mediaId,
                    $message->body,
                    // Solo lo usa document: sin filename el cliente recibe el
                    // archivo con un nombre autogenerado.
                    $file->getClientOriginalName(),
                );
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
        $message->setRelation('sender', $user);

        // El webhook de Meta puede haber movido el estado (sent -> delivered)
        // mientras se armaba esta respuesta, así que se relee de la tabla en
        // vez de confiar en el valor que quedó en memoria.
        $message->status = Message::whereKey($message->getKey())->value('status');

        // Tiempo real: los demás agentes que miran este chat lo ven al instante.
        MessageCreated::dispatch($message);

        $deliveryFailed = $message->status === 'failed';

        return response()->json([
            'data' => $message->toApiArray(),
            'message' => $deliveryFailed
                ? 'El mensaje quedó guardado, pero WhatsApp rechazó la entrega.'
                : 'Mensaje enviado.',
            'delivery_failed' => $deliveryFailed,
        ], 201);
    }

    /**
     * Cada tipo tiene su propio tope en la Cloud API (una imagen aguanta mucho
     * menos que un documento). Se valida acá y no en las reglas porque el tipo
     * recién se conoce después de leer el mime del archivo.
     */
    private function assertWithinTypeLimit(UploadedFile $file, string $type): void
    {
        $maxKilobytes = WhatsappService::maxKilobytesFor($type);

        if ($file->getSize() <= $maxKilobytes * 1024) {
            return;
        }

        $labels = ['image' => 'Las imágenes', 'video' => 'Los videos', 'audio' => 'Los audios', 'document' => 'Los documentos'];

        throw ValidationException::withMessages([
            'media' => [sprintf(
                '%s no pueden superar los %d MB.',
                $labels[$type] ?? 'Los archivos',
                (int) round($maxKilobytes / 1024),
            )],
        ]);
    }
}
