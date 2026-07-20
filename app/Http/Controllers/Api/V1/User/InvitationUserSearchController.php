<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\InvitationUserSearchRequest;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InvitationUserSearchController extends ApiController
{
    /**
     * Search users for invitation flows.
     *
     * Returns users who can be invited to the specified project,
     * excluding the project owner and existing members.
     */
    public function __invoke(InvitationUserSearchRequest $request, Project $project, InvitationService $invitationService): AnonymousResourceCollection
    {
        return TaskMemberResource::collection($invitationService->usersSearch($project, $request->searchTerm()));
    }
}
