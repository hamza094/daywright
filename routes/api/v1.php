<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OAuth\ZoomAuthController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\Project\ActivityController;
use App\Http\Controllers\Api\V1\Project\ConversationController;
use App\Http\Controllers\Api\V1\Project\FeaturesController;
use App\Http\Controllers\Api\V1\Project\InvitationController;
use App\Http\Controllers\Api\V1\Project\MessageController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Project\ProjectInsightsController;
use App\Http\Controllers\Api\V1\Project\ProjectLimitsController;
use App\Http\Controllers\Api\V1\Project\StageController;
use App\Http\Controllers\Api\V1\Project\ZoomMeetingController;
use App\Http\Controllers\Api\V1\ProjectDashboardController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use App\Http\Controllers\Api\V1\Task\TaskFeaturesController;
use App\Http\Controllers\Api\V1\Task\TaskStatusController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\User\AvatarController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\UserInvitationsController;
use App\Http\Controllers\Api\V1\Webhooks\ZoomWebhookController;
use App\Http\Controllers\Api\V1\Zoom\ZoomTokenController;
use App\Http\Middleware\VerifyZoomWebhook;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------*/

// Zoom Webhooks
Route::controller(ZoomWebhookController::class)
    ->middleware(VerifyZoomWebhook::class)
    ->prefix('webhooks/zoom/meetings')
    ->as('webhooks.meetings.')
    ->group(function (): void {
        Route::post('update', 'update')->name('update');

        Route::post('delete', 'delete')->name('delete');

        Route::post('start', 'start')->name('start');

        Route::post('ended', 'ended')->name('ended');

    });

Route::middleware(['auth:sanctum'])->group(function (): void {

    Route::get('/users/me', [UserController::class, 'me'])->name('user.me');

    Route::get('/users/me/zoom-token', [ZoomTokenController::class, 'getUserToken']);

    Route::get('/users/me/zoom-jwt-token', [ZoomTokenController::class, 'getJwtToken']);

    Route::controller(TokenController::class)
        ->prefix('api-tokens')
        ->name('api-tokens.')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/{token}', 'destroy')->name('destroy');
        });

    Route::get('/me/invitations', [UserInvitationsController::class, 'myInvitations'])
        ->name('user.invitations');

    Route::controller(ProjectDashboardController::class)->group(function (): void {
        Route::get('dashboard/chart-data', 'chartData')->name('dashboard.chart-data');
        Route::get('dashboard/insights', 'kpis')
            ->middleware('subscription')
            ->name('dashboard.insights');
        Route::get('dashboard/tasks', 'tasksData')->name('tasks.data');
        Route::get('dashboard/activities', 'activities');
        Route::get('dashboard/projects', 'dashboardProjects');
    })->middleware(['can:owner', 'user']);

    // Return All Stages
    Route::get('/stages', [StageController::class, 'index']);

    // Project Api Resource Routes
    Route::apiResource('/projects', ProjectController::class)->except(['show']);

    // Project Route Prefix
    Route::scopeBindings()->group(function (): void {
        Route::group(['prefix' => 'projects/{project}'], function (): void {
            Route::get('/', [ProjectController::class, 'show'])->name('projects.show')->withTrashed();
            Route::get('/limits', ProjectLimitsController::class)->name('projects.limits')->withTrashed()->can('manage', 'project');

            Route::get('/insights', [ProjectInsightsController::class, 'index'])->name('projects.insights');

            Route::delete('/force', [ProjectController::class, 'delete'])->withTrashed()->can('manage', 'project');
            Route::patch('/restore', [ProjectController::class, 'restore'])->withTrashed()->can('manage', 'project');

            Route::middleware(['can:access,project'])->group(function (): void {

                Route::get('/activities', [ActivityController::class, 'index']);

                // Project Feature Routes
                Route::controller(FeaturesController::class)->group(function (): void {
                    Route::get('export', 'export')->middleware([
                        'subscription',
                        EnsureFeaturesAreActive::using('project-export'),
                    ]);
                    Route::patch('stage', 'stage');
                });

                Route::controller(MessageController::class)
                    ->middleware([
                        'subscription',
                        EnsureFeaturesAreActive::using('project-messaging'),
                    ])
                    ->group(function (): void {
                        Route::post('message', 'message');
                        Route::get('messages/scheduled', 'scheduled');
                        Route::delete('messages/{message}', 'delete');
                    });

                // Chat Conversation Routes
                Route::apiResource('/conversations', ConversationController::class)
                    ->only(['store', 'destroy', 'index']);
            });

            Route::middleware(['can:access,project'])->group(function (): void {
                Route::apiResource('/tasks', TaskController::class)
                    ->except(['destroy'])
                    ->withTrashed(['show', 'index']);
            });

            Route::controller(TaskFeaturesController::class)
                ->name('task.')
                ->prefix('tasks/{task}')
                ->group(function (): void {

                    Route::middleware(['can:manage,task'])->group(function (): void {

                        Route::patch('assign', 'assign')
                            ->name('assign');

                        Route::patch('unassign', 'unassign')
                            ->name('unassign');

                        Route::delete('/remove', 'remove')
                            ->name('remove')
                            ->withTrashed();
                    });

                    Route::middleware(['can:access,task'])->group(function (): void {
                        Route::patch('archive', 'archive')
                            ->name('archive');

                        Route::patch('restore', 'unarchive')
                            ->name('unarchive')
                            ->withTrashed();

                        Route::get('member/search', 'search')
                            ->name('members.search');
                    });
                });

            Route::controller(InvitationController::class)->group(function (): void {
                Route::post('invitations', 'invite')
                    ->name('send.invitation')
                    ->middleware('throttle:invite-actions')
                    ->can('manage', 'project');

                Route::post('invitations/accept', 'accept')
                    ->name('accept.invitation')
                    ->can('canAcceptInvitation', 'project');

                Route::post('invitations/reject', 'reject')
                    ->can('canAcceptInvitation', 'project');

                Route::delete('invitations/{user}', 'cancel')
                    ->withoutScopedBindings()
                    ->name('projects.cancel-invitation');

                Route::delete('members/{user}', 'remove')
                    ->withoutScopedBindings()
                    ->can('manage', 'project');

                Route::get('invitations', 'pending')
                    ->can('manage', 'project')
                    ->name('project.pending.invitation');
            });

            Route::apiResource('/meetings', ZoomMeetingController::class);

        });
    });

    Route::get('users/search', [InvitationController::class, 'search'])
        ->name('users.search');

    Route::apiResource('/users', UserController::class)->except(['store']);
    Route::delete('/users/{user}/force', [UserController::class, 'forceDestroy'])->name('users.forceDestroy');

    Route::group(['prefix' => 'users/{user}'], function (): void {

        Route::delete('/avatar', [AvatarController::class, 'removeAvatar'])->name('user.avatar.remove');

        Route::post('/avatar', [AvatarController::class, 'avatar'])
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

    Route::post('subscriptions', [SubscriptionController::class, 'subscribe'])
        ->name('subscriptions.store');

    Route::middleware(['subscription'])->group(function (): void {
        Route::patch('subscriptions', [SubscriptionController::class, 'swap'])
            ->name('subscription.swap');

        Route::delete('subscriptions', [SubscriptionController::class, 'cancel'])
            ->name('subscription.cancel');
    });

    Route::controller(SubscriptionController::class)
        ->prefix('user')
        ->group(function (): void {

            Route::get('subscriptions', 'subscriptions')
                ->name('user.subscription');

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
