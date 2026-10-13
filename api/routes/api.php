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
use App\Http\Controllers\Api\ContinueListeningController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FreeAudioController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PlaybackLockController;
use App\Http\Controllers\Api\PostAudioController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StreamAudioController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UnsubscribeController;
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

Route::get('/plans', [PlanController::class, 'index']);
Route::get('/tags', [TagController::class, 'index']);
Route::get('/unsubscribe/{user}', UnsubscribeController::class)->middleware('signed')->name('unsubscribe');
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/support', [SettingController::class, 'support']);
Route::get('/feed/free.xml', FeedController::class);
Route::get('/posts/{id}/free-audio', FreeAudioController::class);
Route::get('/audio/{file}', StreamAudioController::class)->middleware('signed:relative')->name('audio.stream');

// Open to everyone; what a post contains depends on who is asking (see PostPolicy).
Route::middleware(['active', 'device'])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::get('/posts/{id}/audio', PostAudioController::class);
});

Route::middleware(['auth:sanctum', 'active', 'device'])->group(function () {
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/subscription', SubscriptionController::class);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::post('/questions', [QuestionController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/continue', ContinueListeningController::class);
    Route::get('/posts/{id}/progress', [ProgressController::class, 'show']);
    Route::put('/posts/{id}/progress', [ProgressController::class, 'update'])->middleware('throttle:120,1');
    Route::post('/playback/heartbeat', [PlaybackLockController::class, 'heartbeat']);
    Route::post('/playback/release', [PlaybackLockController::class, 'release']);

    Route::get('/user', MeController::class);
    Route::post('/logout', LogoutController::class);
    Route::post('/logout-all', LogoutAllController::class);
    Route::post('/phone', StartPhoneVerificationController::class)->middleware('throttle:5,1');
    Route::post('/phone/verify', VerifyPhoneController::class)->middleware('throttle:10,1');
    Route::put('/password', ChangePasswordController::class);
    Route::post('/email/resend', ResendVerificationController::class)->middleware('throttle:3,1');
});
