# Tiempo real (websockets) para chats

Guía para que el panel web y la app móvil reciban mensajes **al instante**, sin
esperar al polleo. Hoy el flujo es:

- **FCM (push):** despierta la app cuando está cerrada o en segundo plano. ✅ ya
  está.
- **Polleo delta:** con la app abierta, cada 15 s pide sólo lo que cambió
  (`updated_since`, `messages_limit`). ✅ ya está — y queda como **red de
  seguridad**.
- **Websockets:** lo de esta guía. Con la app abierta, el mensaje aparece en
  cuanto entra, sin los 15 s de espera.

Los tres se complementan: websocket mientras mirás la app, push cuando no, y
polleo como respaldo si el socket se cae.

## La decisión que hay que tomar primero

Un websocket necesita un **proceso corriendo todo el tiempo** y un **puerto
abierto** para las conexiones. Eso choca con tu hosting actual.

| Opción | Qué es | ¿Sirve en GoDaddy compartido? | Costo |
|--------|--------|-------------------------------|-------|
| **Pusher** | Servicio en la nube (SaaS). Tu Laravel sólo le hace un POST HTTPS para "disparar" el evento; Pusher mantiene las conexiones. | **Sí.** No corrés ningún proceso: sólo salís a internet. | Plan gratis: 200 000 mensajes/día, 100 conexiones simultáneas. De sobra para un contact center. |
| **Laravel Reverb** | Servidor de websockets propio, oficial de Laravel. Gratis y "código propio". | **No en hosting compartido:** necesita un proceso `reverb:start` vivo y un puerto abierto, que GoDaddy compartido no permite. Sí en un VPS. | Gratis, pero requiere un servidor donde puedas correr procesos. |
| **Soketi** | Igual que Reverb pero de la comunidad. Mismo problema de infraestructura. | Igual que Reverb. | Gratis, requiere VPS. |

**La clave que despreocupa la decisión:** los tres hablan **el mismo protocolo**
(el de Pusher). Programás la app una sola vez contra ese protocolo, y cambiar de
proveedor es sólo cambiar variables del `.env`. No hay que reescribir nada.

**Recomendación para tu situación (GoDaddy compartido):** empezá con **Pusher**.
Es lo que usó tu amigo, entra en el hosting que ya tenés, y el plan gratis
alcanza. El día que muevas el backend a un VPS, pasás a Reverb (gratis) cambiando
el `.env`, sin tocar la app.

Lo que sigue está escrito para Pusher; las diferencias con Reverb se marcan.

---

## Backend (Laravel)

### 1. Instalar el cliente

```bash
composer require pusher/pusher-php-server
```

> **Reverb:** en vez de esto, `php artisan install:broadcasting` instala Reverb y
> deja las variables `REVERB_*`. El resto de los pasos (eventos, canales) es
> idéntico.

### 2. Configurar el `.env`

Hoy tenés `BROADCAST_CONNECTION=log` (los eventos se escriben al log y nadie los
recibe). Cambialo y agregá las credenciales del panel de Pusher:

```dotenv
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=tu-app-id
PUSHER_APP_KEY=tu-app-key
PUSHER_APP_SECRET=tu-app-secret
PUSHER_APP_CLUSTER=us2
```

`PUSHER_APP_KEY` es **pública** (va en la app móvil). `PUSHER_APP_SECRET` es
**secreta**: nunca sale del servidor.

### 3. Publicar la config de broadcasting

Tu proyecto todavía no tiene `config/broadcasting.php`. Publicalo:

```bash
php artisan config:publish broadcasting
```

Trae la conexión `pusher` ya armada leyendo las variables de arriba. No hace
falta editarla.

### 4. Autorizar los canales privados

Los canales de un chat son **privados**: sólo el agente que puede ver esa
conversación debe recibir sus mensajes. Laravel resuelve esto con un endpoint
`/broadcasting/auth` que hay que proteger con el **mismo guard** que ya usás
(sesión para el SPA, Bearer para el móvil).

En `bootstrap/app.php`, dentro de `withRouting(...)`, registrá la ruta de
broadcasting bajo `auth:sanctum`:

```php
->withBroadcasting(
    __DIR__.'/../routes/channels.php',
    ['middleware' => ['auth:sanctum']],
)
```

En `routes/channels.php` definí quién entra a cada canal, **reutilizando tu scope
`visibleTo`** (así la autorización del socket usa exactamente las mismas reglas
de acceso que la API REST):

```php
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal de un chat: sólo si la conversación es visible para ese agente.
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::whereKey($conversationId)->visibleTo($user)->exists();
});

// Canal propio de cada agente: avisos de "chat nuevo" o "otro agente lo tomó".
Broadcast::channel('agent.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});
```

### 5. Crear los eventos que se transmiten

Un evento que implementa `ShouldBroadcast` se manda solo al broadcaster. Truco
importante: el payload se arma con **el mismo `toApiArray()`** que ya devuelve la
API REST, así el cliente lo parsea con el modelo que ya tiene.

`app/Events/MessageCreated.php`:

```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    /** Nombre con el que el cliente escucha el evento. */
    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** Mismo formato que la API REST: el cliente lo parsea igual. */
    public function broadcastWith(): array
    {
        return $this->message->toApiArray();
    }
}
```

Con el mismo molde te conviene tener también:

- `MessageStatusUpdated` — cuando el webhook cambia un estado (enviado →
  entregado → leído). Canal `conversation.{id}`.
- `NewConversation` / `ConversationClaimed` — para la bandeja. Canal
  `agent.{userId}` (espejo de lo que ya hace la notificación push).

### 6. Disparar los eventos

Donde ya persistís los mensajes, agregás una línea:

- **Entrantes** — en `WhatsappWebhookController::storeInboundMessage()`, después de
  crear el `$message`:

  ```php
  MessageCreated::dispatch($message);
  ```

- **Salientes** — en `MessageController::store()`, después de confirmar el envío.
- **Estados** — en `handleStatusUpdates()`, tras el `update`.

`ShouldBroadcast` **encola** el envío automáticamente, y vos ya tenés
`QUEUE_CONNECTION=database` con un worker corriendo (`php artisan queue:work`).
Eso significa que en GoDaddy **no corre ningún proceso de websockets**: el worker
sólo hace un POST HTTPS a Pusher. Por eso Pusher entra en hosting compartido y
Reverb no.

---

## Móvil (Flutter)

### 1. Paquete

```bash
flutter pub add pusher_channels_flutter
```

Es el cliente oficial de Pusher. Sirve igual para Reverb (mismo protocolo): sólo
cambiás `host`/`port` en `init`.

### 2. Conectar y suscribir

Al iniciar sesión, te conectás y te suscribís a tu canal de agente; al abrir un
chat, al canal de esa conversación. Los canales privados se autorizan llamando a
`/broadcasting/auth` con el Bearer que ya manda el interceptor de Dio.

```dart
final pusher = PusherChannelsFlutter.getInstance();

await pusher.init(
  apiKey: 'PUSHER_APP_KEY',   // la pública
  cluster: 'us2',
  onEvent: _onEvent,
  onAuthorizer: (channelName, socketId, options) async {
    // /broadcasting/auth está en la raíz del dominio, no bajo /api/v1.
    final res = await dio.post(
      'https://cc.edgarcallisaya.com/broadcasting/auth',
      data: {'socket_id': socketId, 'channel_name': channelName},
    );
    return res.data; // {auth: "..."} — Dio agrega el Bearer solo
  },
);

await pusher.connect();
await pusher.subscribe(channelName: 'private-agent.$miUserId');
// al abrir un chat:
await pusher.subscribe(channelName: 'private-conversation.$conversationId');
```

### 3. Empujar el evento al BLoC

El payload que llega es **idéntico** al de la API, así que se reusa el modelo y
los merges que ya escribimos (`_mergeMessages`, `_upsertConversation`):

```dart
void _onEvent(PusherEvent event) {
  if (event.eventName == 'message.created') {
    final json = jsonDecode(event.data) as Map<String, dynamic>;
    final message = ChatMessage.fromJson(json);
    chatsBloc.add(ChatsRealtimeMessage(message)); // nuevo evento en el bloc
  }
}
```

En el bloc, `ChatsRealtimeMessage` hace lo mismo que un polleo pero para un solo
mensaje: lo fusiona en el hilo abierto y sube el chat en la bandeja.

### 4. El polleo pasa a respaldo

Con el socket conectado, alargá el intervalo del polleo (p. ej. de 15 s a 60 s) o
pausalo, y reanudalo al desconectarse. El delta-polleo que ya está queda como
red de seguridad para cuando el socket no esté disponible.

---

## Probar en local

Sin salir a Pusher podés levantar **Soketi** (servidor Pusher-compatible,
gratis) con Docker:

```bash
docker run -p 6001:6001 quay.io/soketi/soketi:latest
```

Y apuntar el `.env` a `localhost:6001`. Mismo protocolo, cero costo, ideal para
desarrollo. (En producción sobre GoDaddy compartido sigue sin poder correr, por
lo mismo que Reverb.)

## Seguridad

- Los canales son **privados** y se autorizan con tu scope `visibleTo`: un agente
  sólo recibe eventos de los chats que ya podría ver por la API. No hay fuga.
- En la app va sólo `PUSHER_APP_KEY` (pública). `PUSHER_APP_SECRET` nunca sale del
  servidor.

## Resumen de esfuerzo

| Parte | Trabajo |
|-------|---------|
| Backend | Instalar cliente, `config/broadcasting.php`, ruta de auth con sanctum, `channels.php`, 3–4 eventos `ShouldBroadcast`, y un `dispatch` en cada punto donde ya se guarda un mensaje. |
| Móvil | Un paquete, conectar/suscribir con auth por Bearer, un evento nuevo en el `ChatsBloc`, y alargar el polleo cuando el socket está vivo. |
| Infra | Una cuenta de Pusher (gratis). Nada que instalar en el servidor. |
