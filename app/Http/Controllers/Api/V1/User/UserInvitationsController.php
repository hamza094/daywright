<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserInvitationsIndexRequest;
use App\Http\Resources\Api\V1\Project\ProjectInvitationResource;
use App\Services\Project\InvitationService;
use Illuminate\Http\JsonResponse;

class UserInvitationsController extends ApiController
{
    /**
     * List all pending project invitations for the authenticated user.
     *
     * Returns a paginated list of the authenticated user's pending project invitations.
     */
    public function myInvitations(UserInvitationsIndexRequest $request, InvitationService $invitationService): JsonResponse
    {
        $user = $this->authenticatedUser();

        $pendingInvitations = $invitationService->pendingForUser(
            $user,
            $request->perPage(),
            $request->pageNumber(),
        );

        return ProjectInvitationResource::collection($pendingInvitations)->response();
    }
}
