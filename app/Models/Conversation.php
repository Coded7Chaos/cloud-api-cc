<?php

namespace App\Models;

use App\Services\ConversationNotificationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['contact_id', 'assigned_user_id', 'status', 'last_message_at', 'auto_reply_sent'])]
class Conversation extends Model
{
    /** Ventana de atención libre de WhatsApp: horas desde el último mensaje del cliente. */
    public const WINDOW_HOURS = 24;

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'auto_reply_sent' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** El agente que atiende el chat. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Agentes que recibieron el aviso mientras el chat seguía sin dueño. */
    public function notificationRecipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_notification_recipients')
            ->withTimestamps();
    }

    /** Último mensaje del hilo (para la vista previa de la bandeja). */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Último mensaje ENTRANTE: ancla la ventana de 24h de atención al cliente.
     *
     * OJO: un ->where() encadenado ANTES de latestOfMany() no alcanza acá,
     * porque la subquery del MAX(sent_at) se arma desde cero (no hereda los
     * where del builder externo) y terminaría promediando entrantes Y
     * salientes. Por eso el filtro por dirección va en el closure: así
     * también se aplica dentro de la subquery agregada.
     */
    public function latestInboundMessage(): HasOne
    {
        return $this->hasOne(Message::class)
            ->ofMany(['sent_at' => 'max'], fn ($query) => $query->where('direction', 'inbound'))
            ->where('direction', 'inbound');
    }

    /**
     * WhatsApp solo deja mandar mensajes libres dentro de las 24h desde el
     * último mensaje del cliente. Pasado ese plazo, hay que esperar a que
     * el contacto vuelva a escribir (eso reabre la ventana).
     */
    public function canSendFreeform(): bool
    {
        $lastInbound = $this->latestInboundMessage?->sent_at;

        return $lastInbound !== null && $lastInbound->gt(now()->subHours(self::WINDOW_HOURS));
    }

    /**
     * Visibilidad por rol: administrador ve todo, soporte solo lo suyo o lo
     * que todavía nadie tomó (para poder tomarlo).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('administrador')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_user_id', $user->id);

            if ($user->canReceiveNewChatsAt(now())) {
                $q->orWhereNull('assigned_user_id');
            }
        });
    }

    /**
     * "Ventana abierta" como filtro de lista. A propósito usa messages()
     * (hasMany normal) y NO latestInboundMessage() (ofMany): para un chequeo
     * de umbral ">" da el mismo resultado que comparar el máximo (si el
     * máximo de sent_at supera el corte, existe al menos una fila que
     * también lo supera, y viceversa) sin anidar ofMany dentro de whereHas.
     */
    public function scopeWindowOpen(Builder $query): Builder
    {
        return $query->whereHas('messages', fn (Builder $q) => $q
            ->where('direction', 'inbound')
            ->where('sent_at', '>', now()->subHours(self::WINDOW_HOURS)));
    }

    /** Complemento exacto de windowOpen(): ninguna fila inbound dentro de la ventana. */
    public function scopeWindowClosed(Builder $query): Builder
    {
        return $query->whereDoesntHave('messages', fn (Builder $q) => $q
            ->where('direction', 'inbound')
            ->where('sent_at', '>', now()->subHours(self::WINDOW_HOURS)));
    }

    /**
     * Reglas de acceso por fila para ver/responder un chat puntual:
     *  - administrador: nunca bloquea, nunca asigna (solo supervisa).
     *  - ya asignada a otro agente: 404 (no debería ni saber que existe).
     *  - sin asignar y con ventana abierta: se la queda atómicamente el
     *    primero que la pide; si perdió la carrera contra otro agente, 409.
     *  - sin asignar pero ya vencida: no se asigna a quien solo la mira (no
     *    tiene sentido "reclamar" un chat que ya no se puede responder).
     */
    public function authorizeAndClaimFor(User $user): void
    {
        if ($user->hasRole('administrador')) {
            return;
        }

        if ($this->assigned_user_id !== null && $this->assigned_user_id !== $user->id) {
            abort(404);
        }

        if ($this->assigned_user_id === null && ! $user->canReceiveNewChatsAt(now())) {
            abort(403, 'Solo puedes tomar chats nuevos durante tu horario laboral.');
        }

        if ($this->assigned_user_id === null && $this->canSendFreeform()) {
            $claimed = static::whereKey($this->id)
                ->whereNull('assigned_user_id')
                ->update(['assigned_user_id' => $user->id]);

            if ($claimed === 0) {
                abort(409, 'Este chat ya fue tomado por otro agente.');
            }

            $this->assigned_user_id = $user->id;
            $this->setRelation('assignee', $user);

            // La escritura condicional de arriba decide un único ganador. Sólo
            // ese request llega hasta acá y puede retirar los avisos pendientes.
            app(ConversationNotificationService::class)->notifyClaimed($this, $user);
        }
    }
}
