<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\InvitationUsersRequest;
use App\Http\Requests\Api\V1\ProjectInvitationIndexRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\InvitationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends ApiController
{
    /**
     * Service For Invitation Feature
     *
     * App\Service\Api\V1\InvitationService
     */
    public function __construct(private readonly InvitationService $invitationService) {}

    /**
     * Search Users to send project invitation.
     */
    public function search(Project $project, Request $request): AnonymousResourceCollection
    {
        $results = $this->invitationService->usersSearch($request);

        return TaskMemberResource::collection($results);
    }

    /**
     * Send Project Invitation
     *
     * This endpoint allows the project owner to send an invitation to a specific user to become a member of the project.
     *
     * ### Validation:
     * - Additional checks include:
     *   - **Validation Error:** Throw an error if the invitation is sent to the project owner.
     *   - **Validation Error:** Throw an error if a project invitation has already been sent to the user.
     *
     * ### Authorization:
     * - This action can only be performed by the project owner
     */
    public function invite(Project $project, InvitationUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->invitationService->sendInvitationByEmail($project, $validated['email']);

            $user = User::query()->where('email', $validated['email'])->first();

            return response()->json([
                'message' => 'Project invitation sent to '.$user->name,
                'project' => new ProjectResource($project),
                'invited_user' => new InvitedUserResource($user),
            ], 200);

        } catch (ValidationException $ex) {
            return response()->json(['error' => $ex->getMessage()], 422);
        }
    }

    /**
     * Accept Project Invitation
     *
     * This endpoint allows the invited user to accept an invitation and become a member of the project.
     *
     * ### Validation:
     * - Additional checks include:
     *   - **Validation Error:** Throw an error if the invitation is already accepted by users.
     *
     * ### Authorization:
     * - This action can only be performed by invited user.
     */
    public function accept(Project $project): JsonResponse
    {
        try {
            $user = $this->authenticatedUser();

            $this->invitationService->acceptInvitation($project);

            return response()->json([
                'message' => 'You have accepted Project invitation',
                'project' => new ProjectResource($project),
                'accepted_user' => new InvitedUserResource($user),
            ], Response::HTTP_OK);

        } catch (Exception) {
            return response()->json(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject Project Invitation
     *
     * This endpoint allows an invited user to reject a project invitation.
     *
     * ### Authorization:
     * - Ensures only the user who was invited can perform this action.
     */
    public function reject(Project $project): JsonResponse
    {
        $user = $this->authenticatedUser();

        $this->invitationService->rejectInvitation($project, $user);

        return response()->json([
            'message' => 'You have rejected the invitation to join the project.',
            'project' => new ProjectResource($project),
            'user' => new InvitedUserResource($user),
        ], Response::HTTP_OK);
    }

    /**
     * Cancel Project Invitation
     *
     * This endpoint allows the project owner to cancel a user's pending project invitation
     *
     * ### Authorization:
     * - Only the project owner is authorized to perform this action.
     *
     * #### Error (403 Forbidden):
     *
     * Returned if the authenticated user is not authorized to manage the project or if the user doesn't have a pending invitation.
     *
     * ```json
     * {
     *   "message": "This action is unauthorized."
     * }
     * ```
     */
    public function cancel(Project $project, User $user): JsonResponse
    {
        $this->authorize('manage', $project);

        $this->invitationService->cancelInvitation($project, $user);

        return response()->json([
            'message' => 'You have canceled the invitation for '.$user->name.' to join the project.',
            'project' => new ProjectResource($project),
            'user' => new InvitedUserResource($user),
        ], Response::HTTP_OK);
    }

    /**
     * Remove Project Member
     *
     * This endpoint allows the project owner to remove a member from the project.
     *
     * ### Validation:
     * - The user must be an **active member** of the project to be removed.
     *
     * ### Authorization:
     * - Only the project owner is authorized to perform this action.
     */
    public function remove(Project $project, User $user): JsonResponse
    {
        try {
            $this->invitationService->removeMember($user, $project);

            return response()->json([
                'message' => "Member {$user->name} has been removed from the project",
                'user' => new InvitedUserResource($user),
            ], Response::HTTP_OK);
        } catch (ValidationException $ex) {
            return response()->json(['error' => $ex->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get Pending Project Invitations
     *
     * This endpoint retrieves a list of users who have been invited to a specific project but have not yet accepted the invitation.
     *
     * ### Authorization:
     * - Only the project owner is authorized to perform this action.
     *
     *  * @response 200 {
     *   "message": "List of project pending member requests",
     *   "pending_invitations": [
     *     {
     *       "id": 1,
     *       "name": "Jane Doe",
     *       "email": "jane@example.com"
     *     }
     *   ]
     * }
     */
    public function pending(ProjectInvitationIndexRequest $request, Project $project): JsonResponse
    {
        $request->validated();

        $members = $this->invitationService->pendingMembers($project);

        if ($members->isEmpty()) {
            return response()->json([
                'message' => 'No pending project invitation requests found.',
                'pending_invitations' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'List of project pending member requests',
            'pending_invitations' => InvitedUserResource::Collection($members),
        ], 200);
    }
}
