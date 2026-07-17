<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de chats: hilos de conversación con contactos de WhatsApp.
 */
class ConversationController extends Controller
{
    /** Bandeja ordenada por última actividad. */
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with(['contact', 'assignee:id,name,last_name', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($q) {
                // Entrantes sin leer = entrantes cuyo estado no es "read".
                $q->where('direction', 'inbound')->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'read');
                });
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $c) => $this->transform($c));

        return response()->json(['data' => $conversations]);
    }

    /** Un hilo con todos sus mensajes en orden cronológico. */
    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load([
            'contact',
            'assignee:id,name,last_name',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.sender:id,name,last_name',
        ]);

        return response()->json([
            'data' => array_merge($this->transform($conversation), [
                'messages' => $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'type' => $m->type,
                    'body' => $m->body,
                    'status' => $m->status,
                    'sent_at' => $m->sent_at,
                    'created_at' => $m->created_at,
                    'sender' => $m->sender ? [
                        'id' => $m->sender->id,
                        'name' => $m->sender->name,
                    ] : null,
                ])->values(),
            ]),
        ]);
    }

    /** Forma común de una conversación para la bandeja y el detalle. */
    private function transform(Conversation $c): array
    {
        $last = $c->relationLoaded('messages')
            ? $c->messages->last()
            : ($c->relationLoaded('latestMessage') ? $c->latestMessage : null);

        return [
            'id' => $c->id,
            'status' => $c->status,
            'last_message_at' => $c->last_message_at,
            'unread_count' => $c->unread_count ?? 0,
            'contact' => [
                'id' => $c->contact->id,
                'wa_id' => $c->contact->wa_id,
                'name' => $c->contact->profile_name ?: $c->contact->phone ?: $c->contact->wa_id,
                'phone' => $c->contact->phone,
            ],
            'assignee' => $c->assignee ? [
                'id' => $c->assignee->id,
                'name' => trim($c->assignee->name.' '.$c->assignee->last_name),
            ] : null,
            'preview' => $last?->body,
        ];
    }
}
