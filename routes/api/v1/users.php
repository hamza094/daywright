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
    Route::get('/', CurrentUserController::class)->name('show');

    Route::get('invitations', [App\Http\Controllers\Api\V1\User\UserInvitationsController::class, 'myInvitations'])->name('invitations.index');

    Route::controller(SubscriptionController::class)->prefix('subscription')->name('subscription.')->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'store')->middleware(Idempotent::using(scope: IdempotencyScope::User))->name('store');
        Route::patch('/', 'update')->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User)])->name('update');
        Route::delete('/', 'destroy')->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User)])->name('destroy');
    });
});

Route::apiResource('/users', UserController::class)->except(['store']);
Route::delete('/users/{user}/force', ForceDeleteUserController::class)->name('users.forceDestroy')->withTrashed();

Route::group(['prefix' => 'users/{user}'], function (): void {

    Route::delete('/avatar', [AvatarController::class, 'destroy'])->name('user.avatar.remove');

    Route::post('/avatar', [AvatarController::class, 'store'])
        ->name('user.avatar');
});
