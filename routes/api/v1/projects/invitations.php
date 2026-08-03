<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Project\AcceptProjectInvitationController;
use App\Http\Controllers\Api\V1\Project\ProjectInvitationController;
use App\Http\Controllers\Api\V1\Project\ProjectMemberController;
use App\Http\Controllers\Api\V1\Project\RejectProjectInvitationController;
use App\Http\Controllers\Api\V1\User\InvitationUserSearchController;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

Route::post('invitations', [ProjectInvitationController::class, 'store'])
    ->name('send.invitation')
    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:invite-actions', 'tokenAbility:team:write'])
    ->can('manage', 'project');

Route::post('invitations/accept', AcceptProjectInvitationController::class)
    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:team:write'])
    ->name('accept.invitation')
    ->can('canAcceptInvitation', 'project');

Route::post('invitations/reject', RejectProjectInvitationController::class)
    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:team:write'])
    ->name('reject.invitation')
    ->can('canAcceptInvitation', 'project');

Route::get('users/search', InvitationUserSearchController::class)
    ->middleware('tokenAbility:team:read')
    ->name('projects.users.search')
    ->can('manage', 'project');

Route::delete('invitations/{user}', [ProjectInvitationController::class, 'destroy'])
    ->middleware('tokenAbility:team:write')
    ->withoutScopedBindings()
    ->name('projects.cancel-invitation');

Route::delete('members/{user}', ProjectMemberController::class)
    ->middleware('tokenAbility:team:write')
    ->name('projects.members.destroy')
    ->withoutScopedBindings()
    ->can('manage', 'project');

Route::get('invitations', [ProjectInvitationController::class, 'index'])
    ->middleware('tokenAbility:team:read')
    ->can('manage', 'project')
    ->name('project.pending.invitation');
