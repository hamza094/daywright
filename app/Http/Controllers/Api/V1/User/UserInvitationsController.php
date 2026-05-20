<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserInvitationsIndexRequest;
use App\Http\Resources\Api\V1\Project\ProjectInvitationResource;
use Illuminate\Http\JsonResponse;

class UserInvitationsController extends ApiController
{
    /**
     * List all pending project invitations for the authenticated user.
     *
     * Returns a paginated list of the authenticated user's pending project invitations.
     */
    public function myInvitations(UserInvitationsIndexRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $pendingInvitations = $user->inactiveMembers()
            ->with('user')
            ->orderByPivot('created_at')
            ->paginate($request->perPage(), ['*'], 'page', $request->pageNumber())
            ->withQueryString();

        return ProjectInvitationResource::collection($pendingInvitations)->response();
    }
}
