<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class AccountRecoveryOtpNotification extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly int $ttlMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de recuperación de cuenta — AquaSmart')
            ->greeting('Hola')
            ->line('Recibimos una solicitud para recuperar el acceso a tu cuenta de AquaSmart.')
            ->line('Tu código de verificación es:')
            ->line(new HtmlString(
                '<div style="font-size:28px;font-weight:700;letter-spacing:6px;text-align:center;margin:12px 0;">'.
                e($this->code).
                '</div>'
            ))
            ->line("Este código vence en {$this->ttlMinutes} minutos y solo puede usarse una vez.")
            ->line('Si no solicitaste este código, ignora este mensaje: tu cuenta sigue segura.');
    }
}
