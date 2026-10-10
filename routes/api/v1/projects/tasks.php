<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Task\ArchiveTaskController;
use App\Http\Controllers\Api\V1\Task\DestroyTaskAssigneeController;
use App\Http\Controllers\Api\V1\Task\RestoreTaskController;
use App\Http\Controllers\Api\V1\Task\StoreTaskAssigneesController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use App\Http\Controllers\Api\V1\Task\TaskMemberSearchController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::middleware(['can:access,project'])->group(function (): void {
    Route::apiResource('/tasks', TaskController::class)
        ->middlewareFor(['index', 'show'], 'tokenAbility:projects:read')
        ->middlewareFor(['store', 'destroy'], 'tokenAbility:projects:write')
        ->withTrashed(['show', 'index', 'destroy'])
        ->except(['update']);

    // Manual PATCH route for task update (partial update semantics)
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])
        ->middleware('tokenAbility:projects:write')
        ->name('tasks.update');
});

Route::name('task.')
    ->prefix('tasks/{task}')
    ->group(function (): void {
        Route::middleware(['can:manage,task'])->group(function (): void {
            // REST endpoint for assigning task members
            Route::post('assignees', StoreTaskAssigneesController::class)
                ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:projects:write'])
                ->name('assignees.store');

            // REST endpoint for unassigning a task member
            Route::delete('assignees/{user}', DestroyTaskAssigneeController::class)
                ->middleware('tokenAbility:projects:write')
                ->name('assignees.destroy');
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
