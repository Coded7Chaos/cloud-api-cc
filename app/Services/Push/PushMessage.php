<?php

namespace App\Services\Push;

/**
 * Una notificación, independiente del transporte que la lleve.
 *
 * Cada canal la traduce a su formato (payload VAPID, "message" de FCM v1,
 * "aps" de APNs), pero el que la origina no tiene que saber nada de eso.
 */
final class PushMessage
{
    /**
     * @param  array<string, mixed>  $data  Datos que la app recibe junto con la
     *                                      notificación (p. ej. conversation_id
     *                                      para abrir el chat correcto al tocarla).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    /** Ruta a abrir al tocar la notificación. */
    public function url(): string
    {
        return (string) ($this->data['url'] ?? '/chats');
    }

    /**
     * Los datos como strings: tanto FCM como APNs exigen que el diccionario de
     * datos sea plano y de puros strings.
     *
     * @return array<string, string>
     */
    public function stringData(): array
    {
        $flat = [];

        foreach ($this->data as $key => $value) {
            $flat[(string) $key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value) => (string) $value,
                $value === null => '',
                default => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
            };
        }

        return $flat;
    }
}
