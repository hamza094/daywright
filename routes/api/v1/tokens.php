<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TokenController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::controller(TokenController::class)
    ->prefix('api-tokens')
    ->name('api-tokens.')
    ->middleware(['auth', 'session.auth'])
    ->group(function (): void {
        Route::get('/', 'index')
            ->name('index');
        Route::post('/', 'store')
            ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:sensitive-token-mgmt'])
            ->name('store');
        Route::delete('/{token}', 'destroy')
            ->middleware(['throttle:sensitive-token-mgmt'])
            ->name('destroy');
    });
