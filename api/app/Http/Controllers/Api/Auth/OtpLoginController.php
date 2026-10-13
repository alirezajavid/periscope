<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\LoginMethod;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\OtpLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginEvent;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OtpLoginController extends Controller
{
    /** Sign in with phone + SMS code; a first-time phone number signs up (name required). */
    public function __invoke(OtpLoginRequest $request): UserResource|JsonResponse
    {
        $data = $request->validated();
        $user = User::firstWhere('phone', $data['phone']);

        $needsName = $user === null && empty($data['name']);

        if (! OtpCode::verify($data['phone'], OtpPurpose::Login, $data['code'], spend: ! $needsName)) {
            LoginEvent::record($request, LoginMethod::Phone, false, $user);

            throw ValidationException::withMessages(['code' => __('otp.invalid')]);
        }

        if ($needsName) {
            throw ValidationException::withMessages(['name' => __('validation.required', ['attribute' => __('validation.attributes.name')])]);
        }

        $user ??= User::create(['name' => $data['name'], 'phone' => $data['phone']]);

        if ($user->isBlocked()) {
            LoginEvent::record($request, LoginMethod::Phone, false, $user);

            return response()->json(['message' => __('auth.blocked')], 403);
        }

        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now()])->save();
        UserSession::signIn($request, $user, LoginMethod::Phone);

        return new UserResource($user);
    }
}
