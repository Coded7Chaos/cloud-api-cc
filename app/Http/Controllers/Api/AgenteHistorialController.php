<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgenteHistorialController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->is_admin, 403);

        $conversations = Conversation::query()
            ->where(function ($query) use ($user): void {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereHas('messages', fn ($query) => $query
                        ->where('direction', 'outbound')
                        ->where('sender_user_id', $user->id));
            })
            ->with('contact')
            ->withCount([
                'messages',
                'messages as sent_messages_count' => fn ($query) => $query
                    ->where('direction', 'outbound')
                    ->where('sender_user_id', $user->id),
                'messages as received_messages_count' => fn ($query) => $query
                    ->where('direction', 'inbound'),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'contact' => $conversation->contact->profile_name
                    ?: $conversation->contact->phone
                    ?: $conversation->contact->wa_id,
                'status' => $conversation->status,
                'started_at' => $conversation->created_at,
                'last_message_at' => $conversation->last_message_at,
                'messages_count' => $conversation->messages_count,
                'sent_messages_count' => $conversation->sent_messages_count,
                'received_messages_count' => $conversation->received_messages_count,
            ]);

        return response()->json(['data' => $conversations]);
    }
}
