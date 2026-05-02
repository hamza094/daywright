<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use App\Models\Project;
use App\Services\Api\V1\InvitationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AcceptProjectInvitationController extends ApiController
{
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        try {
            $user = $this->authenticatedUser();

            $invitationService->acceptInvitation($project, $user);

            return response()->json([
                'message' => 'You have accepted Project invitation',
                'project' => new ProjectSummaryResource($project),
                'accepted_user' => new InvitedUserResource($user),
            ], Response::HTTP_OK);
        } catch (Exception) {
            return response()->json(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
