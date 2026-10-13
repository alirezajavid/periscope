<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\LoginMethod;
use App\Http\Controllers\Admin\Auth\Concerns\ManagesAdminLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    use ManagesAdminLogin;

    /** Step 2 of every later login: authenticator code or one-time recovery code. */
    public function __invoke(TwoFactorChallengeRequest $request, TwoFactorAuthenticationProvider $totp): UserResource
    {
        $admin = $this->pendingAdmin($request);

        abort_if($admin->two_factor_confirmed_at === null, 403, __('auth.two_factor_required'));

        if (! $this->codeIsValid($request, $admin, $totp)) {
            LoginEvent::record($request, LoginMethod::Email, false, $admin);

            throw ValidationException::withMessages(['code' => __('auth.two_factor_invalid')]);
        }

        return $this->completeLogin($request, $admin);
    }

    private function codeIsValid(TwoFactorChallengeRequest $request, User $admin, TwoFactorAuthenticationProvider $totp): bool
    {
        if ($recovery = $request->validated('recovery_code')) {
            $match = collect($admin->recoveryCodes())->first(fn (string $code) => hash_equals($code, $recovery));

            if ($match !== null) {
                $admin->replaceRecoveryCode($match);
            }

            return $match !== null;
        }

        return $totp->verify(Fortify::currentEncrypter()->decrypt($admin->two_factor_secret), $request->validated('code'));
    }
}
