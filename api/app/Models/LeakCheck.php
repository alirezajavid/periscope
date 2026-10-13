<?php

namespace App\Models;

use App\Enums\LeakCheckResult;
use App\Enums\LeakCheckType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_id', 'type', 'subject', 'found_user_id', 'result'])]
class LeakCheck extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => LeakCheckType::class, 'result' => LeakCheckResult::class];
    }

    /** @return BelongsTo<User, $this> */
    public function foundUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'found_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
