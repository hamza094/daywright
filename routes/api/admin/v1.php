<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\Integration\PaddleController;
use App\Http\Controllers\Api\V1\Admin\ProjectController;
use App\Http\Controllers\Api\V1\Admin\StageController;
use App\Http\Controllers\Api\V1\Admin\StatusController;
use App\Http\Controllers\Api\V1\Admin\TaskController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------*/

Route::group(['prefix' => 'admin'], function (): void {

    Route::middleware(['auth:sanctum', 'verified', 'admin', 'block.api.keys', 'throttle:admin-api'])->group(function (): void {

        // Project Api Resource Routes
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');

        Route::post('/backup/database', [DashboardController::class, 'backup'])->name('backup.database');

        // Public (read) endpoints for stages/statuses — only throttle applied
        Route::apiResource('/stages', StageController::class)
            ->only(['index', 'show'])
            ->middleware(['throttle:admin-mutations']);

        Route::apiResource('/statuses', StatusController::class)
            ->only(['index', 'show'])
            ->middleware(['throttle:admin-mutations']);

        // Mutating admin routes that require 2FA and mutation throttling
        Route::middleware(['2fa.enabled', 'throttle:admin-mutations'])->group(function (): void {
            Route::apiResource('/stages', StageController::class)
                ->except(['index', 'show']);

            Route::apiResource('/statuses', StatusController::class)
                ->except(['index', 'show']);

            Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');

            Route::delete('/projects/bulk-delete', [ProjectController::class, 'bulkDelete'])->name('projects.bulk-delete');

            Route::delete('/tasks/bulk-delete', [TaskController::class, 'bulkDelete'])->name('tasks.bulk-delete');
        });

        Route::get('dashboard/activities', [DashboardController::class, 'activities'])->name('dashboard.activities');

        Route::get('data', [DashboardController::class, 'data'])->name('dashboard.data');

        Route::get('subscriptions/list', [PaddleController::class, 'subscribedUsers'])->name('subscriptions.list');

    });
});
