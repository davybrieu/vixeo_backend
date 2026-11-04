<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail carrying the account's data as a JSON file (App\Services\AccountExport). */
class AccountDataExport extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Vixeo data'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You will find attached everything Vixeo stores about your account: profiles, list, progress and connected devices.'))
            ->line(__("If you didn't ask for it, change your password and sign out the devices you don't know."))
            ->salutation(__('The Vixeo team'))
            ->attachData(
                json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'vixeo-donnees.json',
                ['mime' => 'application/json'],
            );
    }
}
