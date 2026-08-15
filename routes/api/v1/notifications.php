<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationsController;

Route::controller(NotificationsController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('tokenAbility:account:read')
            ->name('index');
        Route::patch('/read', 'markAllAsRead')
            ->middleware('tokenAbility:account:write')
            ->name('markAllAsRead');
        Route::patch('/{notification}/status', 'updateStatus')
            ->middleware('tokenAbility:account:write')
            ->name('updateStatus');
        Route::delete('/{notification}', 'destroy')
            ->middleware('tokenAbility:account:write')
            ->name('destroy');
    });
