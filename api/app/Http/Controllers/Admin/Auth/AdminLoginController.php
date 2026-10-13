<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\LoginMethod;
use App\Http\Controllers\Admin\Auth\Concerns\ManagesAdminLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminLoginRequest;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    use ManagesAdminLogin;

    /** Step 1: email + password. Tells the client which TOTP step comes next. */
    public function __invoke(AdminLoginRequest $request): JsonResponse
    {
        $admin = User::firstWhere('email', $request->validated('email'));

        // Same answer for "no such user", "not an admin" and "wrong password".
        $valid = $admin?->isAdmin() && $admin->password !== null
            && Hash::check($request->validated('password'), $admin->password);

        if (! $valid) {
            LoginEvent::record($request, LoginMethod::Email, false, $admin);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($admin->isBlocked()) {
            LoginEvent::record($request, LoginMethod::Email, false, $admin);

            return response()->json(['message' => __('auth.blocked')], 403);
        }

        $this->startPendingLogin($request, $admin);

        return response()->json([
            'status' => $admin->two_factor_confirmed_at ? 'two_factor_required' : 'two_factor_setup_required',
        ]);
    }
}
