<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $category,
        string $action,
        string $description,
        ?User $actor = null,
        ?User $target = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::create([
            'category' => $category,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor ? $this->name($actor) : null,
            'actor_email' => $actor?->email,
            'target_user_id' => $target?->id,
            'target_name' => $target ? $this->name($target) : null,
            'target_email' => $target?->email,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function name(User $user): string
    {
        return trim($user->name.' '.$user->last_name) ?: $user->email;
    }
}
