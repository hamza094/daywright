<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\ActivityController;
use App\Http\Controllers\Api\V1\Project\ExportProjectController;
use App\Http\Controllers\Api\V1\Project\ForceDeleteProjectController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Project\ProjectInsightsController;
use App\Http\Controllers\Api\V1\Project\ProjectLimitsController;
use App\Http\Controllers\Api\V1\Project\RestoreProjectController;
use App\Http\Controllers\Api\V1\Project\UpdateProjectStageController;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;

Route::get('/', [ProjectController::class, 'show'])
    ->name('projects.show')
    ->middleware('tokenAbility:projects:read')
    ->withTrashed();

Route::get('/limits', ProjectLimitsController::class)
    ->name('projects.limits')
    ->middleware('tokenAbility:projects:read')
    ->withTrashed()
    ->can('manage', 'project');

Route::get('/insights', [ProjectInsightsController::class, 'index'])
    ->name('projects.insights')
    ->middleware('tokenAbility:projects:read');

Route::delete('/force', ForceDeleteProjectController::class)
    ->name('projects.force-delete')
    ->middleware('tokenAbility:projects:write')
    ->withTrashed()
    ->can('manage', 'project');

Route::patch('/restore', RestoreProjectController::class)
    ->name('projects.restore')
    ->middleware('tokenAbility:projects:write')
    ->withTrashed()
    ->can('manage', 'project');

Route::middleware(['can:access,project'])->group(function (): void {

    Route::get('/activities', [ActivityController::class, 'index'])
        ->name('projects.activities')
        ->middleware('tokenAbility:projects:read');

    Route::get('export', ExportProjectController::class)
        ->name('projects.export')
        ->middleware([
            'tokenAbility:projects:read',
            'subscription',
            EnsureFeaturesAreActive::using('project-export'),
        ]);

    Route::patch('stage', UpdateProjectStageController::class)
        ->name('projects.stage.update')
        ->middleware('tokenAbility:projects:write');
});
