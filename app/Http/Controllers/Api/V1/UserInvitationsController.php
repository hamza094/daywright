<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\ProjectInvitaionResource;
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

        // Eager load 'user' relation if ProjectInvitaionResource expects it
        $pendingInvitations = $user->inactiveMembers()->with('user')->get();

        if ($pendingInvitations->isEmpty()) {
            return response()->json([
                'invitations' => [],
                'message' => 'No pending invitations found.',
            ]);
        }

        return response()->json([
            'invitations' => ProjectInvitaionResource::collection($pendingInvitations),
        ]);
    }
}
