<?php

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property ReactionType $type */
#[Fillable(['user_id', 'type'])]
class Reaction extends Model
{
    protected function casts(): array
    {
        return ['type' => ReactionType::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
