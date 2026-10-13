<?php

namespace App\Models;

use App\Enums\LoginMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

#[Fillable(['user_id', 'method', 'ip', 'user_agent', 'successful'])]
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'method' => LoginMethod::class,
            'successful' => 'boolean',
        ];
    }

    public static function record(Request $request, LoginMethod $method, bool $successful, ?User $user = null): void
    {
        static::create([
            'user_id' => $user?->id,
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'successful' => $successful,
        ]);
    }
}
