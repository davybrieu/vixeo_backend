<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail with the code that lets a user choose a new password (App\Services\PasswordResets). */
class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Vixeo password reset code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Here is your code to choose a new password:'))
            ->line('**'.$this->code.'**')
            ->line(__('This code expires in :minutes minutes and works only once.', ['minutes' => $this->minutes]))
            ->line(__("If you didn't ask for it, ignore this e-mail: your password stays the same."))
            ->salutation(__('The Vixeo team'));
    }
}
