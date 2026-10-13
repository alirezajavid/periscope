<?php

namespace App\Console\Commands;

use App\Enums\PostAccess;
use App\Enums\UserStatus;
use App\Jobs\GeneratePersonalizedAudio;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

class BuildWatermarks extends Command
{
    /** How long before the publish time the copies start being built. */
    public const LEAD_HOURS = 3;

    protected $signature = 'periscope:build-watermarks';

    protected $description = 'Queue watermarked copies of soon-to-be-published premium episodes for every active subscriber';

    public function handle(): int
    {
        $queued = 0;

        $posts = Post::visibleSoon(self::LEAD_HOURS)
            ->where('access', PostAccess::Premium)->whereNotNull('audio_path')->get();

        foreach ($posts as $post) {
            // Recently active listeners first, so they are the first to have their copy.
            $subscribers = User::where('status', UserStatus::Active)
                ->whereHas('subscriptions', fn ($q) => $q->current())
                ->whereDoesntHave('personalizedFiles', fn ($q) => $q->where('post_id', $post->id))
                ->orderByDesc('last_login_at')
                ->pluck('id');

            foreach ($subscribers as $userId) {
                GeneratePersonalizedAudio::dispatch($post->id, $userId);
                $queued++;
            }
        }

        $this->info("Queued {$queued} watermark job(s).");

        return self::SUCCESS;
    }
}
