<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use App\Models\Project;
use App\Services\Api\V1\InvitationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RejectProjectInvitationController extends ApiController
{
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $invitationService->rejectInvitation($project, $user);

        return response()->json([
            'message' => 'You have rejected the invitation to join the project.',
            'project' => new ProjectSummaryResource($project),
            'user' => new InvitedUserResource($user),
        ], Response::HTTP_OK);
    }
}
