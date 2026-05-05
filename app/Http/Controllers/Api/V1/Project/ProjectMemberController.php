<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\InvitationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProjectMemberController extends ApiController
{
    public function __invoke(Project $project, User $user, InvitationService $invitationService): JsonResponse
    {
        $invitationService->removeMember($user, $project);

        return response()->json([
            'message' => "Member {$user->name} has been removed from the project",
        ], Response::HTTP_OK);
    }
}
