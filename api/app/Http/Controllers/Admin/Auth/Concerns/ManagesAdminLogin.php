<?php

namespace App\Http\Controllers\Admin\Auth\Concerns;

use App\Enums\LoginMethod;
use App\Http\Resources\UserResource;
use App\Models\AdminLog;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * An admin login has two steps: password (puts the admin "in waiting" in the session)
 * and TOTP (completes the login). Nobody is authenticated between the two.
 */
trait ManagesAdminLogin
{
    private const PENDING_MINUTES = 10;

    private function startPendingLogin(Request $request, User $admin): void
    {
        $request->session()->regenerate();
        $request->session()->put('admin_pending', ['id' => $admin->id, 'at' => now()->timestamp]);
    }

    /** The admin who passed the password step recently, or a 401. */
    private function pendingAdmin(Request $request): User
    {
        $pending = $request->session()->get('admin_pending');
        $fresh = $pending !== null && now()->timestamp - $pending['at'] <= self::PENDING_MINUTES * 60;
        $admin = $fresh ? User::find($pending['id']) : null;

        abort_unless($admin?->isAdmin() && ! $admin->isBlocked(), 401, __('auth.two_factor_not_started'));

        return $admin;
    }

    private function completeLogin(Request $request, User $admin): UserResource
    {
        $request->session()->forget('admin_pending');
        Auth::guard('web')->login($admin); // no remember-me for admins
        $request->session()->regenerate();
        $request->session()->put('admin_two_factor_passed', true);
        $request->session()->put('admin_last_activity', now()->timestamp);

        $admin->forceFill(['last_login_at' => now()])->save();
        LoginEvent::record($request, LoginMethod::Email, true, $admin);
        AdminLog::record('admin.login', $admin, admin: $admin);

        return new UserResource($admin);
    }
}
