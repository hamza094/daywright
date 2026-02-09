<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\DashBoardController;
use App\Http\Controllers\Api\V1\Admin\Integration\PaddleController;
use App\Http\Controllers\Api\V1\Admin\PermissionsController;
use App\Http\Controllers\Api\V1\Admin\ProjectController;
use App\Http\Controllers\Api\V1\Admin\RolePermissionController;
use App\Http\Controllers\Api\V1\Admin\RolesController;
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

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {

        // Project Api Resource Routes
        Route::get('/projects', [ProjectController::class, 'index']);

        Route::get('/tasks', [TaskController::class, 'index']);

        Route::get('/users', [UserController::class, 'index']);

        Route::get('/backup/database', [DashBoardController::class, 'backup']);

        Route::apiResource('/stages', StageController::class);

        Route::apiResource('/statuses', StatusController::class);

        Route::delete('/projects/bulk-delete', [ProjectController::class, 'bulkDelete']);

        Route::delete('/tasks/bulk-delete', [TaskController::class, 'bulkDelete']);

        Route::get('dashboard/activities', [DashBoardController::class, 'activities']);

        Route::get('data', [DashBoardController::class, 'data']);

        Route::get('subscriptions/list', [PaddleController::class, 'subscribedUsers']);

        Route::apiResource('/roles', RolesController::class)->except(['show']);

        Route::apiResource('/permissions', PermissionsController::class)
            ->except(['show']);

        Route::get('/assign/roles/{role}/permissions/{permission}', [RolePermissionController::class, 'assignRolePermission']);

        Route::get('/unAssign/roles/{role}/permissions/{permission}', [RolePermissionController::class, 'unAssignPermission']);

        Route::get('assign/users/{user}/roles/{role}', [RolePermissionController::class, 'assignUserRole']);
    });
});
