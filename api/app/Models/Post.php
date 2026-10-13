<?php

namespace App\Models;

use App\Enums\PostAccess;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Mews\Purifier\Facades\Purifier;

/**
 * @property string|null $body
 * @property PostAccess $access
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int|null $started Only present after withCount in the stats query.
 * @property int|null $completed
 * @property float|string|null $average_listened
 * @property int|null $watermarks_ready Only present after scopeWithWatermarkCounts().
 * @property int|null $watermarks_building
 * @property int|null $watermarks_failed
 */
#[Fillable([
    'title', 'episode_number', 'excerpt', 'body', 'access', 'status', 'published_at',
    'sponsor_name', 'sponsor_url', 'embed_code',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = ['access' => 'premium', 'status' => 'draft'];

    protected function casts(): array
    {
        return [
            'access' => PostAccess::class,
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'audio_mirrored_at' => 'datetime',
        ];
    }

    /** The stored body is always purified HTML; raw editor output never reaches the database. */
    protected function body(): Attribute
    {
        return Attribute::set(fn (?string $html) => $html === null ? null : Purifier::clean($html));
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<PlaybackProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(PlaybackProgress::class);
    }

    /** @return HasMany<PersonalizedFile, $this> */
    public function personalizedFiles(): HasMany
    {
        return $this->hasMany(PersonalizedFile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Published and past its publish time (scheduling needs no scheduler: it is just a date check).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * Replace the post's hashtags; unknown names are created.
     *
     * @param  array<int, string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);

        $this->tags()->sync($ids);
    }

    /**
     * Posts already live or going live within $hours; recently published ones stay in range for a day
     * so late subscribers still get a copy built ahead of time.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleSoon(Builder $query, int $hours): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now()->addHours($hours))
            ->where('published_at', '>=', now()->subDay());
    }

    /**
     * Adds watermarks_ready / watermarks_building / watermarks_failed counts (shown in the admin list).
     *
     * @param  Builder<Post>  $query
     */
    public function scopeWithWatermarkCounts(Builder $query): void
    {
        foreach (['ready', 'building', 'failed'] as $status) {
            $query->withCount(["personalizedFiles as watermarks_{$status}" => fn ($q) => $q->where('status', $status)]);
        }
    }

    public function isVisible(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at <= now();
    }

    public function hasAudio(): bool
    {
        return $this->audio_path !== null;
    }
}
