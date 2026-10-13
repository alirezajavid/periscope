<?php

namespace App\Jobs;

use App\Enums\PersonalizedFileStatus;
use App\Models\PersonalizedFile;
use App\Models\Post;
use App\Models\User;
use App\Services\Watermark\AudioWatermarker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Builds one user's watermarked copy of one episode. Safe to dispatch twice. */
class GeneratePersonalizedAudio implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'watermark';

    /** A listener who pressed play before the copy was ready jumps the line. */
    public const PRIORITY_QUEUE = 'watermark-priority';

    public int $tries = 2;

    public int $timeout = 1900;

    public function __construct(public int $postId, public int $userId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(AudioWatermarker $watermarker): void
    {
        $post = Post::findOrFail($this->postId);
        $user = User::findOrFail($this->userId);

        $file = PersonalizedFile::firstOrCreate(['user_id' => $user->id, 'post_id' => $post->id]);
        if ($file->isUsable()) {
            return;
        }

        $file->forceFill(['status' => PersonalizedFileStatus::Building, 'error' => null])->save();

        $path = "audio/personalized/{$post->id}/{$user->id}.mp3";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        $watermarker->apply($disk->path($post->audio_path), $user->watermark_payload, $disk->path($path));

        if ($post->audio_duration === null) {
            $post->forceFill(['audio_duration' => $watermarker->duration($disk->path($post->audio_path))])->save();
        }

        $file->forceFill([
            'path' => $path,
            'status' => PersonalizedFileStatus::Ready,
            'expires_at' => now()->addDays(PersonalizedFile::KEEP_DAYS),
            'mirrored_at' => null,
        ])->save();

        MirrorFileToForeignHost::dispatch('local', $path, personalizedFileId: $file->id);
    }

    public function failed(Throwable $exception): void
    {
        PersonalizedFile::where('user_id', $this->userId)->where('post_id', $this->postId)->update([
            'status' => PersonalizedFileStatus::Failed,
            'error' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
