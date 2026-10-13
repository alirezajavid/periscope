<?php

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Admin\Auth\AdminMeController;
use App\Http\Controllers\Admin\Auth\CsrfTokenController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\Auth\TwoFactorConfirmController;
use App\Http\Controllers\Admin\Auth\TwoFactorSetupController;
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
});
