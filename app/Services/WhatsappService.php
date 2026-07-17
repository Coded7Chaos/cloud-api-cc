<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cliente delgado sobre la WhatsApp Cloud API (Graph API de Meta).
 *
 * Envío de mensajes y descarga de media al disco privado.
 */
class WhatsappService
{
    private string $phoneNumberId;

    private string $token;

    private string $version;

    public function __construct()
    {
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $this->token = (string) config('services.whatsapp.access_token');
        $this->version = (string) config('services.whatsapp.api_version', 'v21.0');
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->baseUrl("https://graph.facebook.com/{$this->version}")
            ->acceptJson();
    }

    /**
     * Mensaje de texto libre. Solo funciona DENTRO de la ventana de 24h
     * (después de que el cliente te escribió). Devuelve el JSON de Meta,
     * que incluye el wamid del mensaje saliente.
     *
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body): array
    {
        return $this->client()
            ->post("/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $body],
            ])
            ->throw()
            ->json();
    }

    /**
     * Sube una imagen o video a Meta y devuelve el media_id reutilizable para
     * enviar el mensaje saliente.
     *
     * @return array<string, mixed>
     */
    public function uploadMedia(UploadedFile $file): array
    {
        return $this->client()
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $file->getMimeType(),
            ])
            ->throw()
            ->json();
    }

    /**
     * Envía una media ya subida por media_id. $type debe ser image o video.
     *
     * @return array<string, mixed>
     */
    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null): array
    {
        $media = ['id' => $mediaId];

        if ($caption !== null && $caption !== '') {
            $media['caption'] = $caption;
        }

        return $this->client()
            ->post("/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => $type,
                $type => $media,
            ])
            ->throw()
            ->json();
    }

    /**
     * Plantilla aprobada. Es lo ÚNICO que podés mandar para iniciar
     * conversación fuera de la ventana de 24h (ej. "hello_world").
     *
     * $components son los bloques (body/header/button) con sus parámetros, p.ej.:
     *   [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'John']]]]
     * Si la plantilla no tiene variables, dejarlo vacío.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public function sendTemplate(string $to, string $template, string $language = 'en_US', array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->client()
            ->post("/{$this->phoneNumberId}/messages", $payload)
            ->throw()
            ->json();
    }

    /**
     * Marca un mensaje entrante como leído (los dos tildes azules).
     *
     * @return array<string, mixed>
     */
    public function markAsRead(string $waMessageId): array
    {
        return $this->client()
            ->post("/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $waMessageId,
            ])
            ->throw()
            ->json();
    }

    /**
     * Descarga una media por su id y la guarda en el disco privado.
     * Devuelve la metadata lista para MessageMedia::create().
     *
     * Flujo de Meta: 1) GET /{media-id} da una URL temporal (expira en minutos).
     *                2) Esa URL se descarga CON el mismo bearer token.
     *
     * @return array<string, mixed>
     */
    public function downloadMedia(string $mediaId): array
    {
        // Paso 1: pedir la URL temporal y la metadata.
        $meta = $this->client()->get("/{$mediaId}")->throw()->json();

        $url = (string) ($meta['url'] ?? '');
        $mime = $meta['mime_type'] ?? null;

        // Paso 2: descargar el binario (también requiere el token).
        $binary = Http::withToken($this->token)->get($url)->throw()->body();

        $extension = $this->extensionFor($mime);
        $path = 'whatsapp/media/'.$mediaId.($extension ? ".{$extension}" : '');

        Storage::disk('local')->put($path, $binary);

        return [
            'disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $mime,
            'original_filename' => $meta['filename'] ?? null,
            'size' => isset($meta['file_size']) ? (int) $meta['file_size'] : strlen($binary),
            'sha256' => $meta['sha256'] ?? hash('sha256', $binary),
            'wa_media_id' => $mediaId,
        ];
    }

    private function extensionFor(?string $mime): ?string
    {
        if (! $mime) {
            return null;
        }

        // "image/jpeg" -> "jpeg", "application/pdf" -> "pdf"
        return Str::of($mime)->after('/')->before(';')->value() ?: null;
    }
}
