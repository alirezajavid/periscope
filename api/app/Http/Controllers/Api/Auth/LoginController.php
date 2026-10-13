<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\LoginMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): UserResource|JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials)) {
            LoginEvent::record($request, LoginMethod::Email, false, User::where('email', $credentials['email'])->first());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if ($user->isBlocked()) {
            Auth::guard('web')->logout();
            LoginEvent::record($request, LoginMethod::Email, false, $user);

            return response()->json(['message' => __('auth.blocked')], 403);
        }

        $request->session()->regenerate();
        UserSession::start($request, $user);
        $user->forceFill(['last_login_at' => now()])->save();
        LoginEvent::record($request, LoginMethod::Email, true, $user);

        return new UserResource($user);
    }
}
