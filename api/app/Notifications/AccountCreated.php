<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/** Sent when an admin creates an account for someone (e.g. a Telegram subscriber): a link to choose a password. */
class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = Password::createToken($notifiable);
        $url = config('periscope.app_url').'/#/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()).'&welcome=1';

        return (new MailMessage)
            ->subject(__('notifications.account_created_subject'))
            ->line(__('notifications.account_created_line'))
            ->action(__('notifications.set_password'), $url)
            ->line(__('notifications.link_expires', ['minutes' => config('auth.passwords.users.expire')]));
    }
}
