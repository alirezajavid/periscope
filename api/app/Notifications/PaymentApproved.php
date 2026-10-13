<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\JalaliDate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('payments.approved_subject'))
            ->line(__('payments.approved_line'))
            ->line(__('payments.approved_period', [
                'from' => JalaliDate::format($this->subscription->starts_at),
                'to' => JalaliDate::format($this->subscription->ends_at),
            ]));
    }
}
