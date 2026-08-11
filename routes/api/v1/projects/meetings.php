<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\MeetingsController;
use App\Http\Controllers\Api\V1\Project\MeetingZoomJoinTokensController;
use App\Http\Controllers\Api\V1\Project\MeetingZoomStartTokensController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::apiResource('/meetings', MeetingsController::class)
    ->middlewareFor(['index', 'show'], ['can:access,project', 'tokenAbility:projects:read'])
    ->middlewareFor(['store', 'update', 'destroy'], ['can:manage,project', 'tokenAbility:projects:write'])
    ->middlewareFor(['store', 'update'], Idempotent::using(scope: IdempotencyScope::User));

Route::post('/meetings/{meeting}/zoom-tokens/start', MeetingZoomStartTokensController::class)
    ->middleware(['can:access,project', 'tokenAbility:projects:write', Idempotent::using(scope: IdempotencyScope::User)])
    ->name('meetings.zoom-tokens.start');

Route::post('/meetings/{meeting}/zoom-tokens/join', MeetingZoomJoinTokensController::class)
    ->middleware(['can:access,project', 'tokenAbility:projects:read', Idempotent::using(scope: IdempotencyScope::User)])
    ->name('meetings.zoom-tokens.join');
