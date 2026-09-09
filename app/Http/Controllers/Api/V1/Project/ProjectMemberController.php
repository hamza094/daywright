<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\InvitationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class ProjectMemberController extends ApiController
{
    /**
     * Remove a project member.
     *
     * Removes an existing member from the project membership list. When a member is removed,
     * their existing task assignments and conversations remain intact (they are not automatically revoked or deleted).
     */
    #[Endpoint(operationId: 'projects.members')]
    public function __invoke(Project $project, User $user, InvitationService $invitationService): JsonResponse
    {
        $invitationService->removeMember($user, $project);

        return $this->respondWithMessage("Member {$user->name} has been removed from the project");
    }
}
