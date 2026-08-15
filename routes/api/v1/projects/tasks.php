<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Task\ArchiveTaskController;
use App\Http\Controllers\Api\V1\Task\AssignTaskMembersController;
use App\Http\Controllers\Api\V1\Task\RestoreTaskController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use App\Http\Controllers\Api\V1\Task\TaskMemberSearchController;
use App\Http\Controllers\Api\V1\Task\UnassignTaskMemberController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::middleware(['can:access,project'])->group(function (): void {
    Route::apiResource('/tasks', TaskController::class)
        ->middlewareFor(['index', 'show'], 'tokenAbility:projects:read')
        ->middlewareFor(['store', 'update', 'destroy'], 'tokenAbility:projects:write')
        ->withTrashed(['show', 'index', 'destroy']);
});

Route::name('task.')
    ->prefix('tasks/{task}')
    ->group(function (): void {
        Route::middleware(['can:manage,task'])->group(function (): void {
            Route::patch('assign', AssignTaskMembersController::class)
                ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:projects:write'])
                ->name('assign');

            Route::patch('unassign', UnassignTaskMemberController::class)
                ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:projects:write'])
                ->name('unassign');
        });

        Route::middleware(['can:access,task'])->group(function (): void {
            Route::patch('archive', ArchiveTaskController::class)
                ->middleware('tokenAbility:projects:write')
                ->name('archive');

            Route::patch('restore', RestoreTaskController::class)
                ->middleware('tokenAbility:projects:write')
                ->name('unarchive')
                ->withTrashed();

            Route::get('members/search', TaskMemberSearchController::class)
                ->middleware('tokenAbility:projects:read')
                ->name('members.search');
        });
    });
