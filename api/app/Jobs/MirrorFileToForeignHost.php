<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** One-way copy of an uploaded file to the foreign host's disk. */
class MirrorFileToForeignHost implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public string $disk, public string $path, public ?int $postId = null) {}

    public function handle(): void
    {
        $source = Storage::disk($this->disk);

        // The file may have been replaced or deleted since the job was queued.
        if (! $source->exists($this->path)) {
            return;
        }

        Storage::disk(config('periscope.foreign_disk'))->writeStream($this->path, $source->readStream($this->path));

        Post::where('id', $this->postId)->where('audio_path', $this->path)->update(['audio_mirrored_at' => now()]);
    }
}
