<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

class NewEpisode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unsubscribe = URL::signedRoute('unsubscribe', ['user' => $notifiable->id]);

        return (new MailMessage)
            ->subject(__('notifications.new_episode_subject', ['title' => $this->post->title]))
            ->line($this->post->title)
            ->line((string) $this->post->excerpt)
            ->action(__('notifications.listen'), config('periscope.app_url').'/#/posts/'.$this->post->id)
            ->line(__('notifications.unsubscribe_hint', ['url' => $unsubscribe]))
            ->withSymfonyMessage(fn (Email $message) => $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribe}>"));
    }
}
