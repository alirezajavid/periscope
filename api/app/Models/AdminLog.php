<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Fillable(['admin_id', 'action', 'target_type', 'target_id', 'details'])]
class AdminLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /**
     * Record an admin action. Called by every admin write endpoint.
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(string $action, ?Model $target = null, array $details = [], ?User $admin = null): self
    {
        return static::create([
            'admin_id' => ($admin ?? Auth::guard('web')->user())?->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'details' => $details ?: null,
        ]);
    }
}
