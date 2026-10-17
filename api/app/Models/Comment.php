<?php

namespace App\Models;

use App\Models\Concerns\HasReactions;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Comments are public as soon as they are posted; an admin can hide them afterwards.
 * Authors can only delete (soft) their own. Replies are one level deep.
 *
 * @property Carbon|null $hidden_at
 * @property int|null $likes_count Only present after scopeWithReactions().
 * @property int|null $dislikes_count
 */
#[Fillable(['post_id', 'user_id', 'parent_id', 'body'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasReactions, SoftDeletes;

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Not hidden by an admin (soft-deleted ones are already excluded by SoftDeletes).
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeShown(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Replies attach to the top-level comment, so the thread stays one level deep. */
    public function threadRootId(): int
    {
        return $this->parent_id ?? $this->id;
    }
}
