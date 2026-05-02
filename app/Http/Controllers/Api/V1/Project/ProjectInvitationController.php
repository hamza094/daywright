<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\InvitationUsersRequest;
use App\Http\Requests\Api\V1\ProjectInvitationIndexRequest;
use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ProjectInvitationController extends ApiController
{
    public function store(Project $project, InvitationUsersRequest $request, InvitationService $invitationService): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = $invitationService->sendInvitationByEmail($project, $validated['email']);

            return response()->json([
                'message' => "Project invitation sent to {$user->name}",
                'project' => new ProjectSummaryResource($project),
                'invited_user' => new InvitedUserResource($user),
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function index(ProjectInvitationIndexRequest $request, Project $project, InvitationService $invitationService): JsonResponse
    {
        $request->validated();

        $members = $invitationService->pendingMembers($project);

        if ($members->isEmpty()) {
            return response()->json([
                'message' => 'No pending project invitation requests found.',
                'pending_invitations' => [],
            ]);
        }

        return response()->json([
            'message' => 'List of project pending member requests',
            'pending_invitations' => InvitedUserResource::collection($members),
        ]);
    }

    public function destroy(Project $project, User $user, InvitationService $invitationService): JsonResponse
    {
        $this->authorize('manage', $project);

        $invitationService->cancelInvitation($project, $user);

        return response()->json([
            'message' => "You have canceled the invitation for {$user->name} to join the project.",
        ]);
    }
}
