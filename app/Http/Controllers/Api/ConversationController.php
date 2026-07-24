<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationPresenceService;
use Illuminate\Database\Eloquent\Builder;
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
     *
     * Para móvil hay dos parámetros más:
     *   ?per_page=N        pagina la bandeja (el SPA no lo manda y sigue
     *                      recibiendo la lista entera, como siempre).
     *   ?updated_since=ISO devuelve solo los chats que cambiaron desde esa
     *                      marca, para que la app refresque sin volver a
     *                      bajarse la bandeja completa en cada polleo.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $archived = $request->boolean('archived');
        $singleDate = $archived ? $request->date('date') : null;
        $dateFrom = $archived ? ($request->date('date_from') ?? $singleDate) : null;
        $dateTo = $archived ? ($request->date('date_to') ?? $singleDate) : null;
        $updatedSince = $request->date('updated_since');

        // Se toma ANTES de consultar: si un chat cambia mientras se arma la
        // respuesta, el próximo polleo con esta marca lo vuelve a traer. Al
        // revés (marca tomada después) se perdería ese cambio para siempre.
        $syncedAt = now();

        $query = Conversation::query()
            ->visibleTo($user)
            ->tap(fn (Builder $q) => $this->applyMailboxBucket($q, $user, $archived))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($updatedSince, fn ($q) => $q->where('updated_at', '>=', $updatedSince))
            ->with(['contact', 'assignee:id,name,last_name', 'latestMessage', 'latestInboundMessage'])
            ->withCount(['messages as unread_count' => function ($q) {
                // Entrantes sin leer = entrantes cuyo estado no es "read".
                $q->where('direction', 'inbound')->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'read');
                });
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $perPage = $this->perPage($request);
        $paginator = $perPage ? $query->paginate($perPage)->withQueryString() : null;
        $conversations = ($paginator ? $paginator->getCollection() : $query->get())
            ->map(fn (Conversation $c) => $this->transform($c));

        // Para el badge de la barra "Chats archivados" (solo hace falta al mirar la lista activa).
        $archivedCount = $archived
            ? null
            : Conversation::query()
                ->visibleTo($user)
                ->tap(fn (Builder $q) => $this->applyMailboxBucket($q, $user, true))
                ->count();

        return response()->json([
            'data' => $conversations,
            'meta' => array_filter([
                'archived_count' => $archivedCount,
                'synced_at' => $syncedAt->toIso8601String(),
                'current_page' => $paginator?->currentPage(),
                'last_page' => $paginator?->lastPage(),
                'per_page' => $paginator?->perPage(),
                'total' => $paginator?->total(),
            ], fn ($value) => $value !== null),
        ]);
    }

    /**
     * Un soporte conserva en su bandeja activa todos los chats que ya tomó,
     * aunque todavía no haya respondido o haya cerrado la ventana de 24h. Los
     * chats sin dueño sólo aparecen mientras siguen atendibles y el agente se
     * encuentra en horario. El administrador mantiene la división histórica
     * por ventana abierta/cerrada.
     */
    private function applyMailboxBucket(Builder $query, User $user, bool $archived): void
    {
        if ($user->hasRole('administrador')) {
            $archived ? $query->windowClosed() : $query->windowOpen();

            return;
        }

        if ($archived) {
            $query->whereNull('assigned_user_id')->windowClosed();

            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->where('assigned_user_id', $user->id)
                ->orWhere(function (Builder $unassigned): void {
                    $unassigned->whereNull('assigned_user_id')->windowOpen();
                });
        });
    }

    /**
     * null = sin paginar. La bandeja nació devolviendo todo y el SPA cuenta con
     * eso, así que paginar es opt-in: solo se activa si el cliente pide
     * ?per_page. El tope evita que un cliente pida 100.000 de una.
     */
    private function perPage(Request $request): ?int
    {
        if (! $request->filled('per_page')) {
            return null;
        }

        return max(1, min((int) $request->integer('per_page'), 100));
    }

    /**
     * Un hilo con sus mensajes en orden cronológico.
     *
     * ?messages_limit=N trae solo los N más recientes; el resto se pide con
     * GET /conversations/{id}/messages?before=<id> al scrollear hacia arriba.
     * Sin el parámetro devuelve el hilo entero, que es lo que espera el SPA.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $conversation->authorizeAndClaimFor($user);
        $this->markAsReadIfAssignedViewer($conversation, $user);

        $limit = $request->filled('messages_limit')
            ? max(1, min((int) $request->integer('messages_limit'), 200))
            : null;

        $conversation->load([
            'contact',
            'assignee:id,name,last_name',
            'latestInboundMessage',
            // Con límite se piden los ÚLTIMOS N (orden descendente) y se da
            // vuelta la colección; ordenar ascendente y cortar traería los N
            // más viejos, que es justo lo contrario de lo que quiere ver el
            // agente al abrir un chat.
            'messages' => fn ($q) => $limit
                ? $q->orderByDesc('created_at')->orderByDesc('id')->limit($limit)
                : $q->orderBy('created_at'),
            'messages.sender:id,name,last_name',
            'messages.media',
        ]);

        // Se reordena sobre la propia relación, no en una variable aparte:
        // transform() saca la vista previa de messages->last() y con la
        // colección al revés tomaría el mensaje más viejo del hilo.
        if ($limit) {
            $conversation->setRelation('messages', $conversation->messages->reverse()->values());
        }

        $messages = $conversation->messages;

        return response()->json([
            'data' => array_merge($this->transform($conversation), [
                'messages' => $messages->map(fn (Message $m) => $m->toApiArray())->values(),
                // El id más viejo de esta tanda: es el cursor para pedir los
                // anteriores. null cuando ya vino el hilo completo.
                'messages_cursor' => $limit && $messages->isNotEmpty() ? $messages->first()->id : null,
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
