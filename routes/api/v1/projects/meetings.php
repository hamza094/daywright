<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\MeetingsController;
use App\Http\Controllers\Api\V1\Project\MeetingZoomTokensController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::apiResource('/meetings', MeetingsController::class)
    ->middlewareFor(['index', 'show'], ['can:access,project', 'tokenAbility:projects:read'])
    ->middlewareFor(['store', 'update', 'destroy'], ['can:manage,project', 'tokenAbility:projects:write'])
    ->middlewareFor(['store', 'update'], Idempotent::using(scope: IdempotencyScope::User));

Route::post('/meetings/{meeting}/zoom-tokens', MeetingZoomTokensController::class)
    ->middleware(['can:access,project', 'tokenAbility:projects:write'])
    ->name('meetings.zoom-tokens');
