<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fortify is only used for the admin TOTP flow; its own login/register routes stay off.
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Same rules the signup screen shows: 8+ characters, a digit, upper- and lower-case Latin letters.
        Password::defaults(fn () => Password::min(8)->numbers()->mixedCase());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) ($request->input('email') ?? $request->input('phone'))).'|'.$request->ip()));

        // Password-reset and verification emails link to the frontend app.
        ResetPassword::createUrlUsing(fn ($user, string $token) => config('periscope.app_url')
            .'/#/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));
    }
}
