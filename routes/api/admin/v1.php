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

    Route::middleware(['auth:sanctum', 'verified', 'admin', 'session.auth', 'throttle:admin-api'])->group(function (): void {

        // Project Api Resource Routes
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');

        Route::post('/backup/database', [DashboardController::class, 'backup'])
            ->middleware('throttle:sensitive-backup')
            ->name('backup.database');

        // Public (read) endpoints for stages/statuses — inherit parent throttle:admin-api
        Route::apiResource('/stages', StageController::class)
            ->only(['index', 'show']);

        Route::apiResource('/statuses', StatusController::class)
            ->only(['index', 'show']);

        // Mutating admin routes that require 2FA and mutation throttling
        Route::middleware(['2fa.enabled', 'throttle:admin-mutations'])->group(function (): void {
            Route::apiResource('/stages', StageController::class)
                ->except(['index', 'show']);

            Route::apiResource('/statuses', StatusController::class)
                ->except(['index', 'show']);

            Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');

            // REST endpoint for bulk project deletion
            Route::delete('/projects', [ProjectController::class, 'bulkDelete'])->name('projects.destroyMany');

            // REST endpoint for bulk task deletion
            Route::delete('/tasks', [TaskController::class, 'bulkDelete'])->name('tasks.destroyMany');
        });

        Route::get('dashboard/activities', [DashboardController::class, 'activities'])->name('dashboard.activities');

        Route::get('data', [DashboardController::class, 'data'])->name('dashboard.data');

        Route::get('subscriptions/list', [PaddleController::class, 'subscribedUsers'])->name('subscriptions.list');

    });
});
