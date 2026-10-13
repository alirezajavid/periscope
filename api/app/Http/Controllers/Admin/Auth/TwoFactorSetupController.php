<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Admin\Auth\Concerns\ManagesAdminLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class TwoFactorSetupController extends Controller
{
    use ManagesAdminLogin;

    /** First login: create the TOTP secret and recovery codes; the admin confirms with a first code. */
    public function __invoke(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $admin = $this->pendingAdmin($request);

        abort_if($admin->two_factor_confirmed_at !== null, 403, __('auth.admin_only'));

        $enable($admin, force: true);

        return response()->json([
            'secret' => decrypt($admin->two_factor_secret),
            'qr_svg' => $admin->twoFactorQrCodeSvg(),
            'qr_url' => $admin->twoFactorQrCodeUrl(),
            'recovery_codes' => $admin->recoveryCodes(),
        ]);
    }
}
