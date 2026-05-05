<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Illuminate\Http\JsonResponse;

final class RejectProjectInvitationController extends ApiController
{
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $invitationService->rejectInvitation($project, $user);

        return $this->respondWithMessage('You have rejected the invitation to join the project.');
    }
}
