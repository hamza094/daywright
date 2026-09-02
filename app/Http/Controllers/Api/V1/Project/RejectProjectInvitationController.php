<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class RejectProjectInvitationController extends ApiController
{
    /**
     * Reject a project invitation.
     *
     * Declines the authenticated user's pending invitation for the project.
     */
    #[Endpoint(operationId: 'invitations.rejectProject')]
    #[HeaderParameter(name: 'Idempotency-Key', type: 'string', required: true, description: 'Unique key to prevent duplicate rejection requests')]
    #[ScrambleResponse(status: 400, description: 'Bad request - missing or invalid Idempotency-Key header')]
    #[ScrambleResponse(status: 409, description: 'Conflict - idempotency key currently being processed')]
    #[ScrambleResponse(status: 422, description: 'Unprocessable entity - idempotency key reused with different request data')]
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
