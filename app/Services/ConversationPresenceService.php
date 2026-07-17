<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ConversationPresenceService
{
    private const VIEWING_TTL_SECONDS = 12;

    public function markViewing(Conversation $conversation, User $user): void
    {
        Cache::put($this->key($conversation, $user), true, now()->addSeconds(self::VIEWING_TTL_SECONDS));
    }

    public function isViewing(Conversation $conversation, User $user): bool
    {
        return Cache::has($this->key($conversation, $user));
    }

    private function key(Conversation $conversation, User $user): string
    {
        return "conversation:{$conversation->id}:viewer:{$user->id}";
    }
}
