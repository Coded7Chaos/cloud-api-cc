<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Coordina los avisos de chats con su ciclo de asignación.
 *
 * Mientras un chat no tiene dueño se avisa a todos los soportes que están en
 * turno. Cuando uno lo toma, se manda una orden de cancelación exactamente a
 * los usuarios que habían recibido el aviso, aunque ya haya terminado su
 * horario para entonces.
 */
class ConversationNotificationService
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly PushNotificationService $push,
        private readonly ConversationPresenceService $presence,
    ) {}

    public function notifyInbound(Conversation $conversation, Message $message, ?Carbon $at = null): void
    {
        $conversation->loadMissing('assignee', 'contact');

        if ($conversation->assigned_user_id !== null) {
            $this->notifyAssignedAgent($conversation, $message);

            return;
        }

        $agents = $this->schedules->supportAgentsAvailableAt($at ?? now());

        if ($agents->isEmpty()) {
            return;
        }

        // syncWithoutDetaching conserva a quien recibió un aviso anterior del
        // mismo chat. Esa lista completa se usa luego para cancelar a todos.
        $conversation->notificationRecipients()->syncWithoutDetaching($agents->modelKeys());

        $contactName = $this->contactName($conversation);

        foreach ($agents as $agent) {
            $this->push->sendToUser(
                $agent,
                'Nuevo chat de WhatsApp',
                $message->body ?: "Mensaje entrante de {$contactName}",
                [
                    'event' => 'new_chat',
                    'conversation_id' => $conversation->id,
                    'notification_id' => $conversation->id,
                    'url' => "/chats?conversation_id={$conversation->id}",
                    'contact' => $contactName,
                ],
            );
        }
    }

    /** Retira el aviso de todos los aparatos que lo recibieron. */
    public function notifyClaimed(Conversation $conversation, User $winner): void
    {
        $recipients = $conversation->notificationRecipients()->get();

        foreach ($recipients as $recipient) {
            $this->push->sendToUser(
                $recipient,
                'Chat tomado',
                'El chat ya fue tomado por otro agente.',
                [
                    'event' => 'conversation_claimed',
                    'conversation_id' => $conversation->id,
                    'notification_id' => $conversation->id,
                    'claimed_by_user_id' => $winner->id,
                    'claimed_by' => trim($winner->name.' '.$winner->last_name),
                    'url' => '/chats',
                ],
            );
        }

        // Los jobs ya llevan todos los datos necesarios; la tabla sólo sirve
        // mientras el chat está en competencia.
        $conversation->notificationRecipients()->detach();
    }

    private function notifyAssignedAgent(Conversation $conversation, Message $message): void
    {
        $assignee = $conversation->assignee;

        if (! $assignee || $this->presence->isViewing($conversation, $assignee)) {
            return;
        }

        $contactName = $this->contactName($conversation);

        $this->push->sendToUser(
            $assignee,
            'Nuevo mensaje de WhatsApp',
            $message->body ?: "Mensaje entrante de {$contactName}",
            [
                'event' => 'new_message',
                'conversation_id' => $conversation->id,
                'notification_id' => $conversation->id,
                'url' => "/chats?conversation_id={$conversation->id}",
                'contact' => $contactName,
            ],
        );
    }

    private function contactName(Conversation $conversation): string
    {
        return $conversation->contact->profile_name
            ?: $conversation->contact->phone
            ?: $conversation->contact->wa_id;
    }
}
