<?php

namespace App\Models;

use App\Enums\PersonalizedFileStatus;
use Database\Factories\PersonalizedFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PersonalizedFileStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_accessed_at
 */
#[Fillable(['user_id', 'post_id', 'path', 'status', 'error', 'mirrored_at', 'expires_at', 'last_accessed_at'])]
class PersonalizedFile extends Model
{
    /** @use HasFactory<PersonalizedFileFactory> */
    use HasFactory;

    /** Files are kept this many days, then rebuilt on demand (the output for a user is always identical). */
    public const KEEP_DAYS = 7;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'building'];

    protected function casts(): array
    {
        return [
            'status' => PersonalizedFileStatus::class,
            'mirrored_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isUsable(): bool
    {
        return $this->status === PersonalizedFileStatus::Ready
            && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
