<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se transmite cuando entra o sale un mensaje, para que el chat abierto lo
 * muestre al instante sin esperar al polleo.
 *
 * SerializesModels: el evento se encola y se procesa en el worker, así que
 * guarda sólo el id del mensaje y lo relee fresco de la base al transmitirlo
 * (con su media ya adjunta).
 */
class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Message $message) {}

    /** Canal privado de la conversación: sólo lo reciben sus agentes. */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    /** Nombre con el que el cliente escucha el evento. */
    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** Mismo formato que la API REST: el cliente lo parsea con el modelo que ya tiene. */
    public function broadcastWith(): array
    {
        return $this->message->toApiArray();
    }
}
