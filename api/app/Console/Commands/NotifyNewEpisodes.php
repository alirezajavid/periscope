<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewEpisode;
use Illuminate\Console\Command;

class NotifyNewEpisodes extends Command
{
    protected $signature = 'periscope:notify-new-episodes';

    protected $description = 'Email users who opted in about episodes that have just gone live';

    public function handle(): int
    {
        // Only recent posts: importing old episodes must not email everybody.
        $posts = Post::visible()->whereNull('notified_at')->where('published_at', '>=', now()->subDay())->get();

        foreach ($posts as $post) {
            // Claim the post first so an overlapping run cannot send twice.
            if (Post::whereKey($post->id)->whereNull('notified_at')->update(['notified_at' => now()]) === 0) {
                continue;
            }

            User::where('status', UserStatus::Active)->where('notify_new_episode', true)
                ->whereNotNull('email_verified_at')->whereNotNull('email')
                ->each(fn (User $user) => $user->notify(new NewEpisode($post)));

            $this->info("Notified about post {$post->id}.");
        }

        return self::SUCCESS;
    }
}
