<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationsController;

Route::controller(NotificationsController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::patch('/read', 'markAllAsRead')->name('markAllAsRead');
        Route::patch('/{notification}/status', 'updateStatus')->name('updateStatus');
        Route::delete('/{notification}', 'destroy')->name('destroy');
    });
