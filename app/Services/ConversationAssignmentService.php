<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Carbon;

class ConversationAssignmentService
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function assignNewConversation(Conversation $conversation, ?Carbon $at = null): ?User
    {
        if ($conversation->assigned_user_id !== null) {
            return $conversation->assignee;
        }

        $agent = $this->nextAvailableAgent($at ?? now());

        if (! $agent) {
            return null;
        }

        $conversation->forceFill(['assigned_user_id' => $agent->id])->save();
        $conversation->setRelation('assignee', $agent);

        return $agent;
    }

    private function nextAvailableAgent(Carbon $at): ?User
    {
        $ids = $this->schedules->supportAgentsAvailableAt($at)->pluck('id');

        if ($ids->isEmpty()) {
            return null;
        }

        return User::query()
            ->whereKey($ids)
            ->withCount(['assignedConversations as active_conversations_count' => fn ($q) => $q->windowOpen()])
            ->orderBy('active_conversations_count')
            ->orderBy('id')
            ->first();
    }
}
