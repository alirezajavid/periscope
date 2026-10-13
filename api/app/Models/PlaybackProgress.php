<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'post_id', 'last_position', 'max_position', 'completed', 'started_at', 'last_played_at'])]
class PlaybackProgress extends Model
{
    protected $table = 'playback_progress';

    /** Listened this share of the episode = "heard to the end" in the stats. */
    public const COMPLETED_RATIO = 0.9;

    /** @var array<string, mixed> */
    protected $attributes = ['last_position' => 0, 'max_position' => 0, 'completed' => false];

    protected function casts(): array
    {
        return ['completed' => 'boolean', 'started_at' => 'datetime', 'last_played_at' => 'datetime'];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Record a position report. Progress belongs to the post, not to a personalized copy;
     * `max_position` only ever grows, `last_position` follows the listener (they may rewind).
     */
    public static function report(User $user, Post $post, int $position): self
    {
        $progress = static::firstOrNew(['user_id' => $user->id, 'post_id' => $post->id]);

        $progress->started_at ??= now();
        $progress->last_position = $position;
        $progress->max_position = max((int) $progress->max_position, $position);
        $progress->last_played_at = now();

        if ($post->audio_duration && $progress->max_position >= $post->audio_duration * self::COMPLETED_RATIO) {
            $progress->completed = true;
        }

        $progress->save();

        return $progress;
    }
}
