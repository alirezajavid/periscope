<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChangePasswordController extends Controller
{
    /** Changing the password signs the user out of every device, including this one. */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        UserSession::destroyFor($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('auth.password_changed')]);
    }
}
