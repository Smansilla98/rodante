<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expire = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Restablecer contraseña — '.config('app.name', 'Rodante'))
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta en Rodante.')
            ->action('Elegir nueva contraseña', $url)
            ->line('Este enlace vence en '.$expire.' minutos.')
            ->line('Si no pediste este cambio, ignorá este mensaje.');
    }
}
