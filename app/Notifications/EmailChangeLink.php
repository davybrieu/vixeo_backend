<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeLink extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url, public readonly int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmez votre adresse e-mail Vixeo')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Cliquez ci-dessous pour confirmer cette nouvelle adresse e-mail et vous connecter à Vixeo.')
            ->action('Confirmer mon adresse e-mail', $this->url)
            ->line('Ce lien est valable '.$this->minutes.' minutes et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’avez pas demandé ce changement, ignorez cet e-mail.');
    }
}
