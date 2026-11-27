<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailLoginCode extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $minutes = 10,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion Vixeo')
            ->greeting('Bonjour,')
            ->line('Voici votre code de connexion :')
            ->line('**'.$this->code.'**')
            ->line('Ce code est valable '.$this->minutes.' minutes et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’avez pas demandé ce code, ignorez cet e-mail.');
    }
}
