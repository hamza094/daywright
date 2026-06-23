<?php

declare(strict_types=1);
/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\OAuth\ZoomAuthController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardActivitiesController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardChartDataController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardKpisController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardProjectsController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardTasksController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\Project\AcceptProjectInvitationController;
use App\Http\Controllers\Api\V1\Project\ActivityController;
use App\Http\Controllers\Api\V1\Project\ConversationController;
use App\Http\Controllers\Api\V1\Project\ExportProjectController;
use App\Http\Controllers\Api\V1\Project\ForceDeleteProjectController;
use App\Http\Controllers\Api\V1\Project\MeetingsController;
use App\Http\Controllers\Api\V1\Project\MeetingZoomTokensController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Project\ProjectInsightsController;
use App\Http\Controllers\Api\V1\Project\ProjectInvitationController;
use App\Http\Controllers\Api\V1\Project\ProjectLimitsController;
use App\Http\Controllers\Api\V1\Project\ProjectMemberController;
use App\Http\Controllers\Api\V1\Project\ProjectMessageController;
use App\Http\Controllers\Api\V1\Project\RejectProjectInvitationController;
use App\Http\Controllers\Api\V1\Project\RestoreProjectController;
use App\Http\Controllers\Api\V1\Project\ScheduledProjectMessagesController;
use App\Http\Controllers\Api\V1\Project\StageController;
use App\Http\Controllers\Api\V1\Project\UpdateProjectStageController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\Task\ArchiveTaskController;
use App\Http\Controllers\Api\V1\Task\AssignTaskMembersController;
use App\Http\Controllers\Api\V1\Task\RestoreTaskController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use App\Http\Controllers\Api\V1\Task\TaskMemberSearchController;
use App\Http\Controllers\Api\V1\Task\TaskStatusController;
use App\Http\Controllers\Api\V1\Task\UnassignTaskMemberController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\User\AvatarController;
use App\Http\Controllers\Api\V1\User\CurrentUserController;
use App\Http\Controllers\Api\V1\User\ForceDeleteUserController;
use App\Http\Controllers\Api\V1\User\InvitationUserSearchController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\UserInvitationsController;
use App\Http\Controllers\Api\V1\Webhooks\ZoomWebhookController;
use App\Http\Middleware\VerifyZoomWebhook;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------*/

// Zoom Webhooks
Route::controller(ZoomWebhookController::class)
    ->middleware([VerifyZoomWebhook::class, Idempotent::using(scope: IdempotencyScope::Global)])
    ->prefix('webhooks/zoom/meetings')
    ->as('webhooks.meetings.')
    ->group(function (): void {
        Route::post('update', 'update')->name('update');

        Route::post('delete', 'delete')->name('delete');

        Route::post('start', 'start')->name('start');

        Route::post('ended', 'ended')->name('ended');

    });

Route::middleware(['auth:sanctum'])->group(function (): void {

    Route::prefix('users/me')->name('users.me.')->group(function (): void {
        Route::get('/', CurrentUserController::class)->name('show');

        Route::get('invitations', [UserInvitationsController::class, 'myInvitations'])->name('invitations.index');

        Route::controller(SubscriptionController::class)->prefix('subscription')->name('subscription.')->group(function (): void {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'store')->middleware(Idempotent::using(scope: IdempotencyScope::User))->name('store');
            Route::patch('/', 'update')->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User)])->name('update');
            Route::delete('/', 'destroy')->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User)])->name('destroy');
        });
    });

    Route::controller(TokenController::class)
        ->prefix('api-tokens')
        ->name('api-tokens.')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware(Idempotent::using(scope: IdempotencyScope::User))->name('store');
            Route::delete('/{token}', 'destroy')->name('destroy');
        });

    Route::get('dashboard/chart-data', DashboardChartDataController::class)->name('dashboard.chart-data');
    Route::get('dashboard/insights', DashboardKpisController::class)
        ->middleware('subscription')
        ->name('dashboard.insights');
    Route::get('dashboard/tasks', DashboardTasksController::class)->name('tasks.data');
    Route::get('dashboard/activities', DashboardActivitiesController::class)->name('dashboard.activities');
    Route::get('dashboard/projects', DashboardProjectsController::class)->name('dashboard.projects');

    // Return All Stages
    Route::get('/stages', [StageController::class, 'index'])->name('stages.index');

    // Project Api Resource Routes
    Route::apiResource('/projects', ProjectController::class)->except(['show']);

    // Project Route Prefix
    Route::scopeBindings()->group(function (): void {
        Route::group(['prefix' => 'projects/{project}'], function (): void {
            Route::get('/', [ProjectController::class, 'show'])->name('projects.show')->withTrashed();
            Route::get('/limits', ProjectLimitsController::class)->name('projects.limits')->withTrashed()->can('manage', 'project');

            Route::get('/insights', [ProjectInsightsController::class, 'index'])->name('projects.insights');

            Route::delete('/force', ForceDeleteProjectController::class)->name('projects.force-delete')->withTrashed()->can('manage', 'project');
            Route::patch('/restore', RestoreProjectController::class)->name('projects.restore')->withTrashed()->can('manage', 'project');

            Route::middleware(['can:access,project'])->group(function (): void {

                Route::get('/activities', [ActivityController::class, 'index'])->name('projects.activities');

                Route::get('export', ExportProjectController::class)->name('projects.export')->middleware([
                    'subscription',
                    EnsureFeaturesAreActive::using('project-export'),
                ]);

                Route::patch('stage', UpdateProjectStageController::class)->name('projects.stage.update');

                Route::middleware([
                    'subscription',
                    EnsureFeaturesAreActive::using('project-messaging'),
                ])->group(function (): void {
                    Route::post('messages', [ProjectMessageController::class, 'store'])
                        ->middleware(Idempotent::using(scope: IdempotencyScope::User))
                        ->name('projects.messages.store');
                    Route::get('messages/scheduled', ScheduledProjectMessagesController::class)->name('projects.messages.scheduled');
                    Route::delete('messages/{message}', [ProjectMessageController::class, 'destroy'])->name('projects.messages.destroy');
                });

                // Chat Conversation Routes
                Route::apiResource('/conversations', ConversationController::class)
                    ->only(['store', 'destroy', 'index'])
                    ->middleware(['subscription', Idempotent::using(scope: IdempotencyScope::User)], ['only' => ['store']]);
            });

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

            Route::post('invitations', [ProjectInvitationController::class, 'store'])
                ->name('send.invitation')
                ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:invite-actions'])
                ->can('manage', 'project');

            Route::post('invitations/accept', AcceptProjectInvitationController::class)
                ->middleware(Idempotent::using(scope: IdempotencyScope::User))
                ->name('accept.invitation')
                ->can('canAcceptInvitation', 'project');

            Route::post('invitations/reject', RejectProjectInvitationController::class)
                ->middleware(Idempotent::using(scope: IdempotencyScope::User))
                ->name('reject.invitation')
                ->can('canAcceptInvitation', 'project');

            Route::delete('invitations/{user}', [ProjectInvitationController::class, 'destroy'])
                ->withoutScopedBindings()
                ->name('projects.cancel-invitation');

            Route::delete('members/{user}', ProjectMemberController::class)
                ->name('projects.members.destroy')
                ->withoutScopedBindings()
                ->can('manage', 'project');

            Route::get('invitations', [ProjectInvitationController::class, 'index'])
                ->can('manage', 'project')
                ->name('project.pending.invitation');

            Route::apiResource('/meetings', MeetingsController::class)
                ->middlewareFor(['index', 'show'], 'can:access,project')
                ->middlewareFor(['store', 'update', 'destroy'], 'can:manage,project')
                ->middlewareFor(['store', 'update'], Idempotent::using(scope: IdempotencyScope::User));

            Route::post('/meetings/{meeting}/zoom-tokens', MeetingZoomTokensController::class)
                ->can('access,project')
                ->name('meetings.zoom-tokens');

        });
    });

    Route::get('users/search', InvitationUserSearchController::class)
        ->name('users.search');

    Route::apiResource('/users', UserController::class)->except(['store']);
    Route::delete('/users/{user}/force', ForceDeleteUserController::class)->name('users.forceDestroy')->withTrashed();

    Route::group(['prefix' => 'users/{user}'], function (): void {

        Route::delete('/avatar', [AvatarController::class, 'destroy'])->name('user.avatar.remove');

        Route::post('/avatar', [AvatarController::class, 'store'])
            ->name('user.avatar');
    });

    Route::controller(NotificationsController::class)
        ->prefix('notifications')
        ->name('notifications.')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::patch('/read', 'markAllAsRead')->name('markAllAsRead');
            Route::patch('/{notification}/status', 'updateStatus')->name('updateStatus');
            Route::delete('/{notification}', 'destroy')->name('destroy');
        });
    Route::get('task-statuses', TaskStatusController::class)
        ->name('task.status');

    Route::controller(ZoomAuthController::class)
        ->as('oauth.zoom.')
        ->middleware('throttle:oauth2-socialite')
        ->group(function (): void {
            Route::get('oauth/zoom/redirect', 'redirect')->name('redirect');
            Route::get('oauth/zoom/callback', 'callback')->name('callback');
        });

});
