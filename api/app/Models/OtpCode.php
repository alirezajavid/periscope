<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'code_hash', 'purpose', 'expires_at'])]
class OtpCode extends Model
{
    public const UPDATED_AT = null;

    public const LIFETIME_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_AFTER_SECONDS = 60;

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** True while the last code for this phone is too fresh to send another one. */
    public static function resendTooSoon(string $phone): bool
    {
        return static::where('phone', $phone)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_AFTER_SECONDS))
            ->exists();
    }

    /** Create a code for the phone and return the plain digits (only the hash is stored). */
    public static function issue(string $phone, OtpPurpose $purpose): string
    {
        $code = (string) random_int(100000, 999999);

        static::create([
            'phone' => $phone,
            'code_hash' => self::hash($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $code;
    }

    /**
     * Check a code against the newest open one for the phone; wrong guesses burn attempts.
     * With $spend = false a correct code stays usable (used to ask for missing sign-up details).
     */
    public static function verify(string $phone, OtpPurpose $purpose, string $code, bool $spend = true): bool
    {
        $otp = static::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($otp === null || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($otp->code_hash, self::hash($code))) {
            $otp->increment('attempts');

            return false;
        }

        if ($spend) {
            $otp->forceFill(['used_at' => now()])->save();
        }

        return true;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
