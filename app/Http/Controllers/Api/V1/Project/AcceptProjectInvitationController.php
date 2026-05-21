<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Illuminate\Http\JsonResponse;

final class AcceptProjectInvitationController extends ApiController
{
    /**
     * Accept a project invitation.
     *
     * Adds the authenticated user to the project through an existing pending invitation.
     */
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $invitationService->acceptInvitation($project, $user);

        return $this->respondWithData([
            'project' => (new ProjectSummaryResource($project))->resolve(),
            'invitation_state' => 'accepted',
        ]);
    }
}
