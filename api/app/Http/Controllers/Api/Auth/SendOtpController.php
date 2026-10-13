<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\SendOtpRequest;
use App\Models\OtpCode;
use App\Support\SmsSender;
use Illuminate\Http\JsonResponse;

class SendOtpController extends Controller
{
    public function __invoke(SendOtpRequest $request, SmsSender $sms): JsonResponse
    {
        $phone = $request->validated('phone');

        if (OtpCode::resendTooSoon($phone)) {
            return response()->json(['message' => __('otp.too_soon')], 429);
        }

        $code = OtpCode::issue($phone, OtpPurpose::Login);
        $sms->send($phone, __('otp.message', ['code' => $code]));

        return response()->json(['message' => __('otp.sent')]);
    }
}
