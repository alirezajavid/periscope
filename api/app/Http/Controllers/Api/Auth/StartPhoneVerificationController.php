<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StartPhoneVerificationRequest;
use App\Models\OtpCode;
use App\Support\SmsSender;
use Illuminate\Http\JsonResponse;

class StartPhoneVerificationController extends Controller
{
    /** Signed-in user asks for a code to attach (and verify) a phone number. */
    public function __invoke(StartPhoneVerificationRequest $request, SmsSender $sms): JsonResponse
    {
        $phone = $request->validated('phone');

        if (OtpCode::resendTooSoon($phone)) {
            return response()->json(['message' => __('otp.too_soon')], 429);
        }

        $sms->send($phone, __('otp.message', ['code' => OtpCode::issue($phone, OtpPurpose::Verify)]));

        return response()->json(['message' => __('otp.sent')]);
    }
}
