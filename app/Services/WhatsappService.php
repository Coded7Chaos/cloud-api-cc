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
    /**
     * Tamaño máximo (en KB) que acepta la Cloud API según el tipo. Son límites
     * de Meta, no nuestros: mandar de más devuelve error y el mensaje queda
     * como "failed" sin que el agente sepa por qué.
     */
    private const MAX_KILOBYTES = [
        'image' => 5120,      // 5 MB
        'video' => 16384,     // 16 MB
        'audio' => 16384,     // 16 MB
        'document' => 102400, // 100 MB
    ];

    private string $phoneNumberId;

    private string $token;

    private string $version;

    /**
     * Tipo de mensaje de WhatsApp para un mime dado. Cualquier cosa que no sea
     * imagen, video o audio viaja como "document" (PDF, Office, zip, txt…),
     * que es justo lo que espera la Cloud API.
     */
    public static function typeForMime(?string $mime): string
    {
        $mime = strtolower((string) $mime);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    /** Límite en KB para ese tipo de media. */
    public static function maxKilobytesFor(string $type): int
    {
        return self::MAX_KILOBYTES[$type] ?? self::MAX_KILOBYTES['document'];
    }

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
     * Envía una media ya subida por media_id. $type es image, video, audio o
     * document.
     *
     * Dos particularidades de la Cloud API que hay que respetar o el envío
     * falla / llega mal:
     *   - audio NO admite caption (Meta rechaza el mensaje).
     *   - document admite filename, y sin él al cliente le llega con un nombre
     *     autogenerado en vez del real.
     *
     * @return array<string, mixed>
     */
    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null): array
    {
        $media = ['id' => $mediaId];

        if ($type !== 'audio' && $caption !== null && $caption !== '') {
            $media['caption'] = $caption;
        }

        if ($type === 'document' && $filename !== null && $filename !== '') {
            $media['filename'] = $filename;
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
