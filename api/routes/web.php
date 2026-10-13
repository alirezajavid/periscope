<?php

use App\Http\Controllers\Api\Auth\GoogleCallbackController;
use App\Http\Controllers\Api\Auth\GoogleRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/auth/google')->group(function () {
    Route::get('/redirect', GoogleRedirectController::class);
    Route::get('/callback', GoogleCallbackController::class);
});
