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
        ->withTrashed(['show', 'index', 'destroy']);
});

Route::name('task.')
    ->prefix('tasks/{task}')
    ->group(function (): void {
        Route::middleware(['can:manage,task'])->group(function (): void {
            Route::patch('assign', AssignTaskMembersController::class)
                ->middleware(Idempotent::using(scope: IdempotencyScope::User))
                ->name('assign');

            Route::patch('unassign', UnassignTaskMemberController::class)
                ->middleware(Idempotent::using(scope: IdempotencyScope::User))
                ->name('unassign');
        });

        Route::middleware(['can:access,task'])->group(function (): void {
            Route::patch('archive', ArchiveTaskController::class)
                ->name('archive');

            Route::patch('restore', RestoreTaskController::class)
                ->name('unarchive')
                ->withTrashed();

            Route::get('members/search', TaskMemberSearchController::class)
                ->name('members.search');
        });
    });
