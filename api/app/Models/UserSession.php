<?php

namespace App\Models;

use App\Enums\LoginMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

#[Fillable(['user_id', 'session_id', 'browser_family', 'os_family', 'ip', 'last_activity_at'])]
class UserSession extends Model
{
    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Browser and OS family of a User-Agent. Versions are deliberately ignored:
     * a session is only invalidated when the family changes.
     *
     * @return array{browser: string, os: string}
     */
    public static function families(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => 'Other',
        };

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung',
            (bool) preg_match('/Firefox\/|FxiOS/', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS/', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            // An installed iOS PWA sends WebKit's UA without the "Safari" token.
            $os === 'iOS' && str_contains($ua, 'AppleWebKit') => 'Safari',
            default => 'Other',
        };

        return ['browser' => $browser, 'os' => $os];
    }

    /** Sign the user in on this request: fresh session id, session registry, last login, audit event. */
    public static function signIn(Request $request, User $user, LoginMethod $method): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        static::start($request, $user);
        $user->forceFill(['last_login_at' => now()])->save();
        LoginEvent::record($request, $method, true, $user);
    }

    /** Register the current (freshly regenerated) session for the user. */
    public static function start(Request $request, User $user): void
    {
        $families = static::families($request->userAgent());

        $request->session()->put('device', $families);

        static::updateOrCreate(
            ['session_id' => $request->session()->getId()],
            [
                'user_id' => $user->id,
                'browser_family' => $families['browser'],
                'os_family' => $families['os'],
                'ip' => $request->ip(),
                'last_activity_at' => now(),
            ],
        );
    }

    /** Destroy the user's sessions (all, or all except one) in Redis and in the registry. */
    public static function destroyFor(User $user, ?string $exceptSessionId = null): void
    {
        $sessions = $user->sessions()
            ->when($exceptSessionId, fn ($query) => $query->where('session_id', '!=', $exceptSessionId))
            ->get();

        foreach ($sessions as $session) {
            Session::getHandler()->destroy($session->session_id);
            $session->delete();
        }
    }
}
