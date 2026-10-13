<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutAllController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\OtpLoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendVerificationController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\SendOtpController;
use App\Http\Controllers\Api\Auth\StartPhoneVerificationController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Auth\VerifyPhoneController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class)->middleware('throttle:10,1');
Route::post('/login', LoginController::class)->middleware('throttle:login');
Route::post('/otp/send', SendOtpController::class)->middleware('throttle:10,1');
Route::post('/otp/login', OtpLoginController::class)->middleware('throttle:login');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1');
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth:sanctum', 'active', 'device'])->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', LogoutController::class);
    Route::post('/logout-all', LogoutAllController::class);
    Route::post('/phone', StartPhoneVerificationController::class)->middleware('throttle:5,1');
    Route::post('/phone/verify', VerifyPhoneController::class)->middleware('throttle:10,1');
    Route::put('/password', ChangePasswordController::class);
    Route::post('/email/resend', ResendVerificationController::class)->middleware('throttle:3,1');
});
