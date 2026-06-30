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
}
