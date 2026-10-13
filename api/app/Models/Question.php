<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property QuestionStatus $status */
#[Fillable(['user_id', 'body'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status' => QuestionStatus::class, 'answered_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function answeredInPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'answered_in_post_id');
    }
}
