<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\InvitationService;
use Illuminate\Http\JsonResponse;

final class AcceptProjectInvitationController extends ApiController
{
    public function __invoke(Project $project, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $invitationService->acceptInvitation($project, $user);

        return $this->respondWithMessage('You have accepted Project invitation');
    }
}
