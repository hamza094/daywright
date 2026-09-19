<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Webhooks\ZoomWebhookController;
use App\Http\Middleware\VerifyZoomWebhook;

// Zoom Webhooks
Route::controller(ZoomWebhookController::class)
    ->middleware([VerifyZoomWebhook::class, 'throttle:webhook-ingress'])
    ->prefix('webhooks/zoom/meetings')
    ->as('webhooks.meetings.')
    ->group(function (): void {
        Route::post('created', 'created')->name('created');

        Route::post('update', 'update')->name('update');

        Route::post('delete', 'delete')->name('delete');

        Route::post('start', 'start')->name('start');

        Route::post('ended', 'ended')->name('ended');

    });
