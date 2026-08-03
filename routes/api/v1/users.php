<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\User\AvatarController;
use App\Http\Controllers\Api\V1\User\CurrentUserController;
use App\Http\Controllers\Api\V1\User\ForceDeleteUserController;
use App\Http\Controllers\Api\V1\User\UserController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::prefix('users/me')->name('users.me.')->group(function (): void {
    Route::get('/', CurrentUserController::class)
        ->middleware('tokenAbility:account:read')
        ->name('show');

    Route::get('invitations', [App\Http\Controllers\Api\V1\User\UserInvitationsController::class, 'myInvitations'])
        ->middleware('tokenAbility:team:read')
        ->name('invitations.index');

    Route::controller(SubscriptionController::class)->prefix('subscription')->name('subscription.')->group(function (): void {
        Route::get('/', 'show')
            ->middleware('tokenAbility:account:read')
            ->name('show');
        Route::post('/', 'store')
            ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write'])
            ->name('store');
        Route::patch('/', 'update')
            ->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write'])
            ->name('update');
        Route::delete('/', 'destroy')
            ->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write'])
            ->name('destroy');
    });
});

Route::apiResource('/users', UserController::class)
    ->except(['store'])
    ->middlewareFor(['index', 'show'], 'tokenAbility:team:read')
    ->middlewareFor(['update', 'destroy'], 'tokenAbility:team:write');

Route::delete('/users/{user}/force', ForceDeleteUserController::class)
    ->middleware('tokenAbility:team:write')
    ->name('users.forceDestroy')
    ->withTrashed();

Route::group(['prefix' => 'users/{user}'], function (): void {

    Route::delete('/avatar', [AvatarController::class, 'destroy'])
        ->middleware('tokenAbility:team:write')
        ->name('user.avatar.remove');

    Route::post('/avatar', [AvatarController::class, 'store'])
        ->middleware('tokenAbility:team:write')
        ->name('user.avatar');
});
