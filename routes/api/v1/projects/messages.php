<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\ConversationController;
use App\Http\Controllers\Api\V1\Project\ProjectMessageController;
use App\Http\Controllers\Api\V1\Project\ScheduledProjectMessagesController;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::middleware([
    'subscription',
    EnsureFeaturesAreActive::using('project-messaging'),
])->group(function (): void {
    Route::post('messages', [ProjectMessageController::class, 'store'])
        ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:projects:write'])
        ->name('projects.messages.store');
    Route::get('messages/scheduled', ScheduledProjectMessagesController::class)
        ->middleware('tokenAbility:projects:read')
        ->name('projects.messages.scheduled');
    Route::delete('messages/{message}', [ProjectMessageController::class, 'destroy'])
        ->middleware('tokenAbility:projects:write')
        ->name('projects.messages.destroy');
});

// Chat Conversation Routes
Route::apiResource('/conversations', ConversationController::class)
    ->only(['store', 'destroy', 'index'])
    ->middlewareFor(['index'], 'tokenAbility:projects:read')
    ->middlewareFor(['store', 'destroy'], ['tokenAbility:projects:write', 'subscription', Idempotent::using(scope: IdempotencyScope::User)]);
