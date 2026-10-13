<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyPhoneRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use Illuminate\Validation\ValidationException;

class VerifyPhoneController extends Controller
{
    public function __invoke(VerifyPhoneRequest $request): UserResource
    {
        $data = $request->validated();

        if (! OtpCode::verify($data['phone'], OtpPurpose::Verify, $data['code'])) {
            throw ValidationException::withMessages(['code' => __('otp.invalid')]);
        }

        $request->user()->forceFill(['phone' => $data['phone'], 'phone_verified_at' => now()])->save();

        return new UserResource($request->user());
    }
}
