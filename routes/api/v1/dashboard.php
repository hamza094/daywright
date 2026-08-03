<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Dashboard\DashboardActivitiesController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardChartDataController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardKpisController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardProjectsController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardTasksController;

Route::get('dashboard/chart-data', DashboardChartDataController::class)
    ->middleware('tokenAbility:account:read')
    ->name('dashboard.chart-data');

Route::get('dashboard/insights', DashboardKpisController::class)
    ->middleware(['subscription', 'tokenAbility:account:read'])
    ->name('dashboard.insights');

Route::get('dashboard/tasks', DashboardTasksController::class)
    ->middleware('tokenAbility:account:read')
    ->name('tasks.data');

Route::get('dashboard/activities', DashboardActivitiesController::class)
    ->middleware('tokenAbility:account:read')
    ->name('dashboard.activities');

Route::get('dashboard/projects', DashboardProjectsController::class)
    ->middleware('tokenAbility:account:read')
    ->name('dashboard.projects');
