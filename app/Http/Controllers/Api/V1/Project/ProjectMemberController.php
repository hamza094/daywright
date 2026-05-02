<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ProjectMemberController extends ApiController
{
    public function __invoke(Project $project, User $user, InvitationService $invitationService): JsonResponse
    {
        try {
            $invitationService->removeMember($user, $project);

            return response()->json([
                'message' => "Member {$user->name} has been removed from the project",
            ], Response::HTTP_OK);
        } catch (ValidationException $exception) {
            return response()->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
