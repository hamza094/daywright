<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Auth routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::name('api.v1.')
    ->group(function (): void {
        Route::group(['prefix' => 'v1'], function (): void {

            Route::group(['middleware' => 'guest:api'], function (): void {

                Route::post('register', [RegisterController::class, 'register'])
                    ->name('auth.register')
                    ->middleware('throttle:auth-register');

                Route::post('login', [LoginController::class, 'login'])
                    ->name('auth.login')
                    ->middleware('throttle:auth-login');

                Route::post('/forgot-password', [ResetPasswordController::class, 'sendResetLink'])
                    ->name('password.email')
                    ->middleware('throttle:password-email');

                Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])
                    ->name('password.update')
                    ->middleware('throttle:password-reset');

                Route::get('/password/reset/{token}', [VerificationController::class, 'resetForm'])->name('password.reset');

            });

            Route::group(['middleware' => ['auth:sanctum']], function (): void {

                Route::post('/email/verify/{user}', [VerificationController::class, 'verify'])
                    ->name('verification.verify')
                    ->middleware('throttle:verification');

                Route::post('/email/resend/{user}', [VerificationController::class, 'resend'])
                    ->name('verification.resend')
                    ->middleware('throttle:verification');

                Route::post('logout', [LoginController::class, 'logout'])->name('auth.logout');

            });
        });
    });

Route::fallback(fn () => response()->json(['message' => 'Not Found.'], 404));
