<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class RejectProjectInvitationController extends ApiController
{
    /**
     * Reject a project invitation.
     *
     * Declines the authenticated user's pending invitation for the project.
     */
    #[Endpoint(operationId: 'invitations.rejectProject')]
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $invitationService->rejectInvitation($project, $user);

        return $this->respondWithData([
            'project' => (new ProjectSummaryResource($project))->resolve(),
            'invitation_state' => 'rejected',
        ]);
    }
}
