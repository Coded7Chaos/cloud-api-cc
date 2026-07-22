<?php

namespace App\Services\Push;

use App\Models\User;

/**
 * Un transporte de notificaciones.
 *
 * Hay uno por plataforma porque el canal que despierta una app en segundo
 * plano lo decide el sistema operativo y no se puede autohospedar: iOS obliga
 * a pasar por APNs y Android por FCM. Lo que sí es nuestro es el emisor: los
 * drivers de acá hablan HTTP directo contra esas APIs, sin SDK de terceros.
 */
interface PushChannel
{
    /** Nombre corto para logs y para el switch de config/push.php. */
    public function name(): string;

    /** false si faltan credenciales: el canal se saltea sin romper el envío. */
    public function isConfigured(): bool;

    /**
     * Entrega el mensaje a todos los dispositivos que este canal atiende.
     * No lanza: un transporte caído no puede tumbar a los otros ni al webhook.
     */
    public function send(User $user, PushMessage $message): void;
}
