<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:api')->group(function (): void {
    Route::post('register', [RegisterController::class, 'register'])
        ->name('auth.register')
        ->middleware('throttle:auth-register');

    Route::post('login', [LoginController::class, 'login'])
        ->name('auth.login')
        ->middleware('throttle:auth-login');

    Route::post('forgot-password', [ResetPasswordController::class, 'sendResetLink'])
        ->name('password.email')
        ->middleware('throttle:password-email');

    Route::post('reset-password', [ResetPasswordController::class, 'resetPassword'])
        ->name('password.update')
        ->middleware('throttle:password-reset');

    Route::get('password/reset/{token}', [VerificationController::class, 'resetForm'])
        ->name('password.reset');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('email/verify/{user}', [VerificationController::class, 'verify'])
        ->name('verification.verify')
        ->middleware('throttle:verification');

    Route::post('email/resend/{user}', [VerificationController::class, 'resend'])
        ->name('verification.resend')
        ->middleware('throttle:verification');

    Route::post('logout', [LoginController::class, 'logout'])->name('auth.logout');
});
