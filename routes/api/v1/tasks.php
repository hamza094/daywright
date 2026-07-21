<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\StageController;
use App\Http\Controllers\Api\V1\Task\TaskStatusController;

// Return All Stages
Route::get('/stages', [StageController::class, 'index'])->name('stages.index');

Route::get('task-statuses', TaskStatusController::class)
    ->name('task.status');
