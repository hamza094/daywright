<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TokenController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::controller(TokenController::class)
    ->prefix('api-tokens')
    ->name('api-tokens.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware(Idempotent::using(scope: IdempotencyScope::User))->name('store');
        Route::delete('/{token}', 'destroy')->name('destroy');
    });
