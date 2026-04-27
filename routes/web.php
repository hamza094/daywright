<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\Auth\SpaAuthController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', fn () => view('welcome.home'));

Route::middleware('guest')->group(function (): void {
    Route::get('/register', HomeController::class)->defaults('shouldBootstrapSession', false);
    Route::get('/login', HomeController::class)->defaults('shouldBootstrapSession', false);
    Route::get('/forgot-password', HomeController::class)->defaults('shouldBootstrapSession', false);
    Route::get('/auth/callback/{provider}', HomeController::class)->defaults('shouldBootstrapSession', false);
    Route::get('/api/v1/password/reset/{token}', HomeController::class)->defaults('shouldBootstrapSession', false);
});

Route::get('/2fa/code', HomeController::class)->defaults('shouldBootstrapSession', false);
Route::get('/api/v1/email/verify/{user}', HomeController::class)->defaults('shouldBootstrapSession', false);

// SPA session auth endpoints (cookie-based via Sanctum stateful)
Route::prefix('api/v1/session')->group(function (): void {
    // Login establishes a session; guest-only
    Route::post('login', [SpaAuthController::class, 'loginSpa'])
        ->middleware(['guest', 'throttle:auth-login']);

    // Logout destroys the current session; requires authenticated session (use sanctum to treat stateful requests)
    Route::post('logout', [SpaAuthController::class, 'logoutSpa'])->middleware('auth:sanctum');
});

// OAuth session-based endpoints (redirect + provider callback)
Route::prefix('api/v1/auth')
    ->name('oauth.')
    ->middleware('throttle:oauth2-socialite')
    ->group(function (): void {
        Route::get('/redirect/{provider}', [OAuthController::class, 'redirect'])
            ->name('redirect');

        Route::get('/callback/{provider}', [OAuthController::class, 'callback'])
            ->name('callback');
    });

// Two-Factor routes: keep session callback under `web` middleware so
// session store is available for `login-confirm`. Endpoints that require
// an authenticated user use `auth:sanctum` to validate stateful requests.
Route::prefix('api/v1/twofactor')
    ->name('twofactor.')
    ->group(function (): void {
        // Session-based confirmation (called after initial login sets session)
        Route::post('login-confirm', [TwoFactorController::class, 'twoFactorLogin'])
            ->name('login-confirm')
            ->middleware('throttle:two-factor');

        // The rest of the two-factor management endpoints are stateful and
        // expect an authenticated user via Sanctum (stateful cookie auth).
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('setup', [TwoFactorController::class, 'prepareTwoFactor'])
                ->name('setup')
                ->middleware('throttle:two-factor');

            Route::post('confirm', [TwoFactorController::class, 'confirmTwoFactor'])
                ->name('confirm')
                ->middleware('throttle:two-factor');

            Route::get('fetch-user', [TwoFactorController::class, 'getUserStatus'])->name('fetch-user');

            Route::get('recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])
                ->middleware('2fa.enabled')
                ->name('recovery-codes');

            Route::delete('disable', [TwoFactorController::class, 'disableTwoFactorAuth'])->name('disable');
        });
    });

// SPA Routes
Route::get('{path}', HomeController::class)->where('path', '(.*)');
