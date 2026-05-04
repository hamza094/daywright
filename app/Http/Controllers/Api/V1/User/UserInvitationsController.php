<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Project\ProjectInvitationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserInvitationsController extends ApiController
{
    /**
     * List all pending project invitations for the authenticated user.
     */
    public function myInvitations(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $pendingInvitations = $user->inactiveMembers()->with('user')->get();

        return ProjectInvitationResource::collection($pendingInvitations)->response();
    }
}
