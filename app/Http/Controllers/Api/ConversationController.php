<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de chats: hilos de conversación con contactos de WhatsApp.
 */
class ConversationController extends Controller
{
    public function __construct(private readonly ConversationPresenceService $presence) {}

    /**
     * Bandeja ordenada por última actividad. Administrador ve todo; soporte
     * solo lo suyo o lo que todavía nadie tomó. ?archived=1 trae los chats
     * cuya ventana de 24h ya cerró (en vez de los activos); combinado con
     * ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD, filtra por fecha de registro
     * del chat. `date=YYYY-MM-DD` sigue aceptado como compatibilidad para un
     * solo día. Cada chat queda fijo en la fecha en la que se creó.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $archived = $request->boolean('archived');
        $singleDate = $archived ? $request->date('date') : null;
        $dateFrom = $archived ? ($request->date('date_from') ?? $singleDate) : null;
        $dateTo = $archived ? ($request->date('date_to') ?? $singleDate) : null;

        $conversations = Conversation::query()
            ->visibleTo($user)
            ->when($archived, fn ($q) => $q->windowClosed(), fn ($q) => $q->windowOpen())
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->with(['contact', 'assignee:id,name,last_name', 'latestMessage', 'latestInboundMessage'])
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

        // Para el badge de la barra "Chats archivados" (solo hace falta al mirar la lista activa).
        $archivedCount = $archived ? null : Conversation::query()->visibleTo($user)->windowClosed()->count();

        return response()->json([
            'data' => $conversations,
            'meta' => ['archived_count' => $archivedCount],
        ]);
    }

    /** Un hilo con todos sus mensajes en orden cronológico. */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $conversation->authorizeAndClaimFor($user);
        $this->markAsReadIfAssignedViewer($conversation, $user);

        $conversation->load([
            'contact',
            'assignee:id,name,last_name',
            'latestInboundMessage',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.sender:id,name,last_name',
            'messages.media',
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
                    'media' => $m->media->map(fn ($media) => [
                        'id' => $media->id,
                        'url' => $media->url(),
                        'mime_type' => $media->mime_type,
                        'original_filename' => $media->original_filename,
                        'size' => $media->size,
                    ])->values(),
                    'sender' => $m->sender ? [
                        'id' => $m->sender->id,
                        'name' => $m->sender->name,
                    ] : null,
                ])->values(),
            ]),
        ]);
    }

    private function markAsReadIfAssignedViewer(Conversation $conversation, User $user): void
    {
        if ($conversation->assigned_user_id !== $user->id) {
            return;
        }

        $this->presence->markViewing($conversation, $user);

        $conversation->messages()
            ->where('direction', 'inbound')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'read');
            })
            ->update(['status' => 'read']);

        $conversation->setAttribute('unread_count', 0);
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
            'created_at' => $c->created_at,
            'last_message_at' => $c->last_message_at,
            'unread_count' => $c->unread_count ?? 0,
            'can_send' => $c->canSendFreeform(),
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
