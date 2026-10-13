<?php

namespace App\Models;

use App\Enums\MirrorServer;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property UserRole $role
 * @property UserStatus $status
 * @property MirrorServer $preferred_mirror
 */
#[Fillable(['name', 'email', 'phone', 'password', 'notify_new_episode', 'preferred_mirror'])]
#[Hidden(['password', 'remember_token', 'watermark_payload', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Same as the column defaults, so a freshly created model is complete without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'preferred_mirror' => 'ir',
        'notify_new_episode' => true,
    ];

    protected static function booted(): void
    {
        // 128-bit identifier embedded in this user's audio watermark.
        static::creating(function (User $user) {
            $user->watermark_payload ??= bin2hex(random_bytes(16));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'notify_new_episode' => 'boolean',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'preferred_mirror' => MirrorServer::class,
        ];
    }

    /** @return HasMany<UserSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<PersonalizedFile, $this> */
    public function personalizedFiles(): HasMany
    {
        return $this->hasMany(PersonalizedFile::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** "Subscriber" means holding a subscription whose end date has not passed. */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->current()->exists();
    }

    /** Admins and current subscribers may open premium content. */
    public function canReadPremium(): bool
    {
        return $this->isAdmin() || $this->hasActiveSubscription();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }
}
