<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\UpdateEmailRequest;
use App\Http\Resources\UserResource;

class UpdateEmailController extends Controller
{
    /** Fix a mistyped address right after signing up. Once verified, the email can no longer be changed here. */
    public function __invoke(UpdateEmailRequest $request): UserResource
    {
        $user = $request->user();

        abort_if($user->hasVerifiedEmail(), 403, __('auth.email_locked'));

        $user->forceFill(['email' => $request->validated('email')])->save();
        $user->sendEmailVerificationNotification();

        return new UserResource($user);
    }
}
