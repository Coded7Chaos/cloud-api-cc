# API del panel

La misma API sirve al panel web y a las apps móviles. Las rutas se definen una
sola vez en `routes/panel.php` y `routes/api.php` las monta en dos prefijos:

| Prefijo   | Para qué                                                            |
|-----------|---------------------------------------------------------------------|
| `/api`    | Sin versión. Lo que ya consumían el SPA React y la app Flutter.      |
| `/api/v1` | Canónico para móvil. Un futuro `/api/v2` podrá romper el contrato sin dejar tiradas a las apps ya instaladas. |

Las dos superficies son idénticas hoy. **Clientes nuevos: usar `/api/v1`.**

## Autenticación

El guard `auth:sanctum` acepta dos formas y decide sola cuál aplica:

### Web (SPA de mismo origen) — cookie de sesión

No cambió nada. El SPA carga desde Laravel, recibe la cookie `XSRF-TOKEN` y la
reenvía como cabecera `X-XSRF-TOKEN`; axios ya lo hace con `withCredentials` +
`withXSRFToken`. El CSRF sigue activo: un POST sin la cabecera da **419**.

Para que funcione, el dominio del panel tiene que estar en
`SANCTUM_STATEFUL_DOMAINS` (si está vacío, Sanctum usa `APP_URL`).

### Móvil — token Bearer

```http
POST /api/v1/login
Content-Type: application/json

{ "email": "...", "password": "...", "device_name": "ios" }
```

```json
{ "token": "3|xxxxxxxx...", "user": { "id": 1, "role": { "permissions": [...] } } }
```

A partir de ahí, en cada request:

```http
Authorization: Bearer 3|xxxxxxxx...
```

Detalles que importan:

- El token se emite cuando la petición **no** trae sesión, o cuando manda
  `device_name`. El SPA nunca recibe token.
- **Un token por `device_name`**: volver a loguearse en el mismo teléfono
  revoca el anterior en vez de acumular sesiones huérfanas. Conviene mandar un
  nombre estable por aparato (`ApiClient.deviceName` en la app Flutter).
- `POST /logout` revoca **solo** el token del aparato que la pide (los otros
  teléfonos del agente siguen andando) y de paso borra su registro de push.
- Por defecto los tokens no caducan. Se puede poner un límite en minutos con
  `SANCTUM_EXPIRATION` (y limpiar con `php artisan sanctum:prune-expired`).
- `POST /login` está limitado a 10 intentos por minuto; el resto de la API, a
  120 por minuto y por usuario.

Los permisos son los mismos por token que por sesión: los resuelve el rol del
usuario en cada request, no van dentro del token.

## Endpoints

Sin autenticar:

| Método | Ruta                    | Notas                                  |
|--------|-------------------------|----------------------------------------|
| POST   | `/login`                | Devuelve `{token, user}` o `{user}`.   |
| POST   | `/forgot-password`      | 6/min.                                 |
| POST   | `/reset-password`       | 6/min.                                 |
| GET    | `/invitations/status`   | 12/min.                                |
| POST   | `/invitations/accept`   | 6/min.                                 |

Autenticados:

| Método | Ruta                                     | Permiso                     |
|--------|------------------------------------------|-----------------------------|
| GET    | `/user`                                  | —                           |
| POST   | `/logout`                                | —                           |
| GET    | `/roles`                                 | `roles.ver`                 |
| POST   | `/roles`                                 | `roles.crear`               |
| PUT    | `/roles/{id}`                            | `roles.editar`              |
| DELETE | `/roles/{id}`                            | `roles.eliminar`            |
| GET    | `/permissions`                           | `roles.ver`                 |
| GET    | `/conversations`                         | `conversaciones.ver`        |
| GET    | `/conversations/{id}`                    | `conversaciones.ver`        |
| GET    | `/conversations/{id}/messages`           | `conversaciones.ver`        |
| POST   | `/conversations/{id}/messages`           | `conversaciones.responder`  |
| GET    | `/media/{id}`                            | por conversación            |
| GET/POST/PUT/DELETE | `/users`, `/users/{id}`     | `usuarios.*`                |
| GET    | `/schedules`                             | `horarios.ver`              |
| PUT    | `/users/{id}/schedule`                   | `horarios.editar`           |
| GET    | `/audit-logs`                            | `auditoria.ver`             |
| GET/POST/DELETE | `/devices`                      | —                           |
| GET    | `/push/public-key`                       | —                           |
| POST/DELETE | `/push/subscriptions`               | —                           |

## Paginación y sincronización

**La paginación es opt-in.** Sin `per_page`, `/conversations` devuelve la lista
entera, que es lo que espera el SPA web. Con `per_page`, `meta` trae
`current_page`, `last_page`, `per_page` y `total`.

```
GET /api/v1/conversations?per_page=20&page=2
```

Filtros: `archived=1` (chats con la ventana de 24 h cerrada), `date_from` /
`date_to` (solo con `archived`), y para refrescar barato:

```
GET /api/v1/conversations?updated_since=2026-07-21T10:01:00+00:00
```

`updated_since` sale del `meta.synced_at` de la llamada anterior. El filtro es
`>=` a propósito: `updated_at` se guarda con precisión de segundo, y es
preferible repetir un chat que perderse un cambio.

### Mensajes

El detalle del chat trae el hilo completo salvo que se lo acote:

```
GET /api/v1/conversations/{id}?messages_limit=30
```

Devuelve los **30 más recientes** en orden cronológico, más
`data.messages_cursor` (el id del más viejo de la tanda). Con ese cursor se
piden los anteriores al scrollear hacia arriba:

```
GET /api/v1/conversations/{id}/messages?before=39&limit=30
→ { "data": [...], "meta": { "has_more": true, "next_cursor": 21 } }
```

Es cursor y no `?page=N` porque el hilo crece por abajo mientras el agente
scrollea hacia arriba: con offsets fijos, cada mensaje nuevo corre la ventana y
hace que se repitan o se salteen filas.

## Adjuntos

`media[].url` en las respuestas apunta a `/api/media/{id}`. Es una **URL
estable** (antes era firmada y caducaba a los 5 minutos) para que el caché de
imágenes del móvil sirva de algo. No lleva firma, así que va autenticada: hay
que mandarle el `Authorization: Bearer` igual que a cualquier otro endpoint
(`CachedNetworkImage` lo acepta en `httpHeaders`; ver `ApiClient.mediaHeaders()`).

La autorización es por conversación: si el chat no es visible para quien pide,
responde **404** (no 403, para no confirmar que ese adjunto existe).

## Notificaciones

Tres canales, todos detrás de `PushNotificationService`, que abanica a los que
tengan credenciales y saltea el resto. El envío va en cola (`SendPushNotification`)
para no frenar el webhook de Meta.

| Canal    | Para        | Credenciales            |
|----------|-------------|-------------------------|
| Web Push | panel web   | `WEBPUSH_VAPID_*`       |
| APNs     | iOS         | `APNS_*`                |
| FCM      | Android     | `FCM_*`                 |

Los emisores son propios (`app/Services/Push/`): hablan HTTP directo contra
APNs y FCM, sin SDK de terceros. El canal de transporte sí lo impone el sistema
operativo y no se puede autohospedar — iOS obliga a pasar por APNs y Android
por FCM, porque el modo Doze mata los sockets propios en segundo plano. El
envío de mensajes por FCM no tiene costo.

Registro del aparato, desde la app:

```http
POST /api/v1/devices
{ "token": "<token de APNs o FCM>", "platform": "ios", "device_name": "iPhone de Ana" }
```

Es idempotente: conviene llamarlo en cada arranque, porque el token rota
(reinstalación, restore de backup, limpieza de datos). Se da de baja solo
cuando el agente cierra sesión, o con `DELETE /api/v1/devices`.

Si Apple responde 410/`Unregistered` o FCM `UNREGISTERED`, el dispositivo se
borra solo: son avisos de que la app se desinstaló.

## Variables de entorno

Ver `.env.example`. Las que se agregaron para esto:
`SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_EXPIRATION`, `CORS_ALLOWED_ORIGINS`,
`APNS_KEY_ID` / `APNS_TEAM_ID` / `APNS_BUNDLE_ID` / `APNS_PRIVATE_KEY(_PATH)` /
`APNS_PRODUCTION`, `FCM_PROJECT_ID` / `FCM_CREDENTIALS(_PATH)` / `FCM_CHANNEL_ID`.

Sin credenciales de push, la app funciona igual: el canal se saltea en silencio.
