<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de recuperación de contraseña.
 *
 * El link apunta al SPA (mismo dominio), no a una vista de Laravel: React
 * lee "token" y "email" de la query string en /reset-password y llama a
 * POST /api/reset-password para completar el cambio.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Recupera tu contraseña - Cloud API CC')
            ->greeting('Hola,')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace vence en 60 minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este correo: tu contraseña actual seguirá funcionando.');
    }
}
