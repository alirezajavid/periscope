<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\LoginMethod;
use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleCallbackController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $app = config('periscope.app_url');

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect("{$app}/#/login?error=google");
        }

        $user = $this->findOrCreateUser($google->getId(), $google->getEmail(), (string) $google->getName());

        if ($user->isBlocked()) {
            LoginEvent::record($request, LoginMethod::Google, false, $user);

            return redirect("{$app}/#/login?error=blocked");
        }

        UserSession::signIn($request, $user, LoginMethod::Google);

        return redirect("{$app}/#/");
    }

    private function findOrCreateUser(string $googleId, ?string $email, string $name): User
    {
        $linked = SocialAccount::where('provider', 'google')->where('provider_id', $googleId)->first();
        if ($linked !== null) {
            return $linked->user;
        }

        // Google has verified this address, so an existing email account can safely be linked.
        $user = $email ? User::firstWhere('email', $email) : null;
        $user ??= User::create(['name' => $name !== '' ? $name : (string) $email, 'email' => $email]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => $googleId]);

        return $user;
    }
}
