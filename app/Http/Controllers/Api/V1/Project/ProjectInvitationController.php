<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Project\InvitationUsersRequest;
use App\Http\Requests\Api\V1\Project\ProjectInvitationIndexRequest;
use App\Http\Resources\Api\V1\Project\ProjectInvitationResource;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\InvitationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

final class ProjectInvitationController extends ApiController
{
    /**
     * Invite a user to a project.
     *
     * Sends a project invitation to the supplied email address and returns the created invitation resource.
     */
    #[Endpoint(operationId: 'invitations.create')]
    public function store(Project $project, InvitationUsersRequest $request, InvitationService $invitationService): JsonResponse
    {
        $data = $request->toDto();

        $user = $invitationService->sendInvitationByEmail($project, $data->email);

        $invitation = $user->inactiveMembers()
            ->with('user')
            ->whereKey($project->getKey())
            ->firstOrFail();

        return $this->respondCreated(new ProjectInvitationResource($invitation));
    }

    /**
     * List pending project invitations.
     *
     * Returns the released pending invitation view for a project.
     * Use `filter[status]=pending` to retrieve the supported invitation slice.
     * This endpoint intentionally returns a bounded, non-paginated list.
     */
    #[Endpoint(operationId: 'invitations.list')]
    public function index(ProjectInvitationIndexRequest $request, Project $project, InvitationService $invitationService): JsonResponse
    {
        $request->validated();

        $members = $invitationService->pendingMembers($project);

        return InvitedUserResource::collection($members)->response();
    }

    /**
     * Cancel a pending project invitation.
     *
     * Revokes a pending invitation for the targeted user.
     */
    #[Endpoint(operationId: 'invitations.destroy')]
    public function destroy(Project $project, User $user, InvitationService $invitationService): JsonResponse
    {
        $this->authorize('manage', $project);

        $invitationService->cancelInvitation($project, $user);

        return $this->respondWithMessage("You have canceled the invitation for {$user->name} to join the project.");
    }
}
