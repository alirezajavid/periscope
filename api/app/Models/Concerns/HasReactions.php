<?php

namespace App\Models\Concerns;

use App\Enums\ReactionType;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Likes / dislikes for posts and comments.
 *
 * @property int|null $likes_count Only present after scopeWithReactions().
 * @property int|null $dislikes_count
 */
trait HasReactions
{
    /** @return HasMany<Reaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * The reader's own reaction; constrained to the reader in scopeWithReactions().
     *
     * @return HasOne<Reaction, $this>
     */
    public function myReaction(): HasOne
    {
        return $this->hasOne(Reaction::class);
    }

    /**
     * Adds likes_count, dislikes_count and the reader's own reaction.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithReactions(Builder $query, ?User $reader): void
    {
        $query->withCount([
            'reactions as likes_count' => fn ($q) => $q->where('type', ReactionType::Like),
            'reactions as dislikes_count' => fn ($q) => $q->where('type', ReactionType::Dislike),
        ]);

        $reader
            ? $query->with(['myReaction' => fn ($q) => $q->where('user_id', $reader->id)])
            : $query->with(['myReaction' => fn ($q) => $q->whereRaw('1 = 0')]);
    }

    /** Set, change or (with null) remove the user's reaction. */
    public function react(User $user, ?ReactionType $type): void
    {
        if ($type === null) {
            $this->reactions()->where('user_id', $user->id)->delete();

            return;
        }

        $this->reactions()->updateOrCreate(['user_id' => $user->id], ['type' => $type]);
    }

    /** @return array{likes: int, dislikes: int, mine: ReactionType|null} */
    public function reactionSummary(?User $reader): array
    {
        if (! array_key_exists('likes_count', $this->getAttributes())) {
            $this->loadCount([
                'reactions as likes_count' => fn ($q) => $q->where('type', ReactionType::Like),
                'reactions as dislikes_count' => fn ($q) => $q->where('type', ReactionType::Dislike),
            ]);
        }

        $mine = $this->relationLoaded('myReaction')
            ? $this->myReaction
            : ($reader ? $this->reactions()->where('user_id', $reader->id)->first() : null);

        return [
            'likes' => (int) $this->likes_count,
            'dislikes' => (int) $this->dislikes_count,
            'mine' => $mine?->type,
        ];
    }
}
