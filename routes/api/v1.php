<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApiScopeController;
use App\Http\Controllers\Api\V1\Project\ProjectController;

// Webhooks
require __DIR__.'/v1/webhooks.php';

// Public Routes
Route::get('/scopes', [ApiScopeController::class, 'index'])->name('scopes.index');

// Authenticated Routes
Route::middleware(['auth:sanctum', 'throttle:user-ceiling'])->group(function (): void {
    require __DIR__.'/v1/users.php';
    require __DIR__.'/v1/tokens.php';
    require __DIR__.'/v1/dashboard.php';
    require __DIR__.'/v1/notifications.php';
    require __DIR__.'/v1/tasks.php';
    require __DIR__.'/v1/oauth.php';

    // Global Project Routes
    Route::apiResource('/projects', ProjectController::class)
        ->middlewareFor('index', 'tokenAbility:projects:read')
        ->middlewareFor(['store', 'update', 'destroy'], 'tokenAbility:projects:write')
        ->except(['show']);

    // Nested Project Routes
    Route::scopeBindings()->group(function (): void {
        Route::group(['prefix' => 'projects/{project}'], function (): void {
            require __DIR__.'/v1/projects/core.php';
            require __DIR__.'/v1/projects/tasks.php';
            require __DIR__.'/v1/projects/messages.php';
            require __DIR__.'/v1/projects/invitations.php';
            require __DIR__.'/v1/projects/meetings.php';
        });
    });
});
