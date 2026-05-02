<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Task\TaskMemberResource;
use App\Services\Api\V1\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InvitationUserSearchController extends ApiController
{
    public function __invoke(Request $request, InvitationService $invitationService): AnonymousResourceCollection
    {
        return TaskMemberResource::collection($invitationService->usersSearch($request));
    }
}
