<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
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
        $url = rtrim(config('app.url'), '/').'/accept-invitation?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Invitación a Cloud API CC')
            ->greeting('Hola,')
            ->line('Te invitaron a crear tu cuenta en Cloud API CC.')
            ->line('Para activar tu acceso, establece una contraseña segura desde el siguiente enlace.')
            ->action('Crear mi contraseña', $url)
            ->line('Este enlace vence en 60 minutos.')
            ->line('Si ya estableciste tu contraseña, puedes ignorar este correo.');
    }
}
