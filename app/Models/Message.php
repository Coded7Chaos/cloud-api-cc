<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'conversation_id', 'wa_message_id', 'direction', 'type',
    'body', 'status', 'sender_user_id', 'sent_at',
])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** El agente que lo envió (solo salientes). */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return HasMany<MessageMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(MessageMedia::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Forma de un mensaje en la API. Vive en el modelo porque la comparten el
     * detalle del chat, el historial paginado y la respuesta del envío: si la
     * forma se define en cada controlador, tarde o temprano se desincronizan y
     * el cliente recibe un mensaje distinto según por dónde lo pidió.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing('media', 'sender');

        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'type' => $this->type,
            'body' => $this->body,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'media' => $this->media->map(fn (MessageMedia $media) => [
                'id' => $media->id,
                'url' => $media->url(),
                'mime_type' => $media->mime_type,
                'original_filename' => $media->original_filename,
                'size' => $media->size,
            ])->values(),
            'sender' => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ] : null,
        ];
    }
}
