<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailLoginLink extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre lien de connexion Vixeo')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Cliquez sur le bouton ci-dessous pour vous connecter à Vixeo.')
            ->action('Se connecter à Vixeo', $this->url)
            ->line('Ce lien est valable '.$this->minutes.' minutes et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’avez pas demandé ce lien, ignorez cet e-mail.');
    }
}
