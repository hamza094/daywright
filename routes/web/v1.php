<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\Auth\SpaAuthController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('session')
    ->name('session.')
    ->group(function (): void {
        Route::post('login', [SpaAuthController::class, 'loginSpa'])
            ->name('login')
            ->middleware(['guest', 'throttle:auth-login']);

        Route::post('logout', [SpaAuthController::class, 'logoutSpa'])
            ->name('logout')
            ->middleware(['auth:sanctum', 'throttle:user-ceiling']);
    });

Route::prefix('auth')
    ->name('oauth.')
    ->middleware('throttle:oauth2-socialite')
    ->group(function (): void {
        Route::get('redirect/{provider}', [OAuthController::class, 'redirect'])
            ->name('redirect');

        Route::get('callback/{provider}', [OAuthController::class, 'callback'])
            ->name('callback');
    });

Route::prefix('twofactor')
    ->name('twofactor.')
    ->group(function (): void {
        Route::post('login-confirm', [TwoFactorController::class, 'twoFactorLogin'])
            ->name('login-confirm')
            ->middleware('throttle:two-factor');

        Route::middleware(['auth:sanctum', 'throttle:user-ceiling', 'session.auth'])->group(function (): void {
            Route::post('setup', [TwoFactorController::class, 'prepareTwoFactor'])
                ->name('setup')
                ->middleware('throttle:two-factor');

            Route::post('confirm', [TwoFactorController::class, 'confirmTwoFactor'])
                ->name('confirm')
                ->middleware('throttle:two-factor');

            Route::get('fetch-user', [TwoFactorController::class, 'getUserStatus'])->name('fetch-user');

            Route::post('recovery-codes', [TwoFactorController::class, 'generateRecoveryCodes'])
                ->middleware('2fa.enabled')
                ->name('recovery-codes');

            Route::delete('disable', [TwoFactorController::class, 'disableTwoFactorAuth'])->name('disable');
        });
    });
