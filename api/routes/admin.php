<?php

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Admin\Auth\AdminMeController;
use App\Http\Controllers\Admin\Auth\CsrfTokenController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\Auth\TwoFactorConfirmController;
use App\Http\Controllers\Admin\Auth\TwoFactorSetupController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserSubscriptionController;
use App\Http\Controllers\Admin\WatermarkController;
use Illuminate\Support\Facades\Route;

// Registered in bootstrap/app.php under the /api/admin prefix; uses its own session cookie.

Route::get('/csrf-token', CsrfTokenController::class);
Route::post('/login', AdminLoginController::class)->middleware('throttle:5,1');
Route::post('/two-factor/setup', TwoFactorSetupController::class)->middleware('throttle:10,1');
Route::post('/two-factor/confirm', TwoFactorConfirmController::class)->middleware('throttle:5,1');
Route::post('/two-factor/challenge', TwoFactorChallengeController::class)->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'admin.idle', 'admin'])->group(function () {
    Route::get('/user', AdminMeController::class);
    Route::post('/logout', AdminLogoutController::class);

    Route::apiResource('plans', PlanController::class)->only(['index', 'store', 'update']);
    Route::apiResource('payment-methods', PaymentMethodController::class)->only(['index', 'store', 'update']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
    Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve']);
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::apiResource('posts', PostController::class)->except(['edit', 'create']);
    Route::post('/posts/{id}/restore', [PostController::class, 'restore']);
    Route::post('/posts/{post}/cover', [PostController::class, 'cover']);
    Route::post('/posts/{post}/audio', [PostController::class, 'audio']);
    Route::get('/posts/{post}/watermarks', [WatermarkController::class, 'index']);
    Route::post('/posts/{post}/watermarks/retry', [WatermarkController::class, 'retry']);

    Route::post('/users/{user}/subscriptions', [UserSubscriptionController::class, 'store']);
});
