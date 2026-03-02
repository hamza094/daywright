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

    Route::middleware(['auth:sanctum', 'verified', 'admin', 'throttle:admin-api'])->group(function (): void {

        // Project Api Resource Routes
        Route::get('/projects', [ProjectController::class, 'index']);

        Route::get('/tasks', [TaskController::class, 'index']);

        Route::get('/users', [UserController::class, 'index']);

        Route::post('/users/{user}/grant-admin', [UserController::class, 'grantAdminAccess'])
            ->middleware(['2fa.enabled', 'throttle:admin-mutations']);

        Route::post('/users/{user}/revoke-admin', [UserController::class, 'revokeAdminAccess'])
            ->middleware(['2fa.enabled', 'throttle:admin-mutations']);

        Route::get('/backup/database', [DashboardController::class, 'backup']);

        Route::apiResource('/stages', StageController::class)
            ->middleware(['2fa.enabled', 'throttle:admin-mutations'])
            ->except(['index', 'show']);

        Route::apiResource('/statuses', StatusController::class)
            ->middleware(['2fa.enabled', 'throttle:admin-mutations'])
            ->except(['index', 'show']);

        Route::delete('/projects/bulk-delete', [ProjectController::class, 'bulkDelete'])
            ->middleware(['2fa.enabled', 'throttle:admin-mutations']);

        Route::delete('/tasks/bulk-delete', [TaskController::class, 'bulkDelete'])
            ->middleware(['2fa.enabled', 'throttle:admin-mutations']);

        Route::get('dashboard/activities', [DashboardController::class, 'activities']);

        Route::get('data', [DashboardController::class, 'data']);

        Route::get('subscriptions/list', [PaddleController::class, 'subscribedUsers']);

    });
});
