<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Admin\Auth\Concerns\ManagesAdminLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\TwoFactorCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\AdminLog;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

class TwoFactorConfirmController extends Controller
{
    use ManagesAdminLogin;

    /** Finishes the first-time setup with a code from the authenticator app, and logs the admin in. */
    public function __invoke(TwoFactorCodeRequest $request, ConfirmTwoFactorAuthentication $confirm): UserResource
    {
        $admin = $this->pendingAdmin($request);

        $confirm($admin, $request->validated('code'));
        AdminLog::record('admin.two_factor_enabled', $admin, admin: $admin);

        return $this->completeLogin($request, $admin);
    }
}
