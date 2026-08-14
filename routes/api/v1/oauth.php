<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OAuth\ZoomAuthController;

Route::controller(ZoomAuthController::class)
    ->as('oauth.zoom.')
    ->middleware(['throttle:oauth2-socialite', 'session.auth'])
    ->group(function (): void {
        Route::get('oauth/zoom/redirect', 'redirect')->name('redirect');
        Route::get('oauth/zoom/callback', 'callback')->name('callback');
    });
