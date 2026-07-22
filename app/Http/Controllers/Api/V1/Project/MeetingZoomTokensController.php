<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\MeetingZoomTokensRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Policies\MeetingPolicy;
use App\Services\Zoom\MeetingTokenService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MeetingZoomTokensController extends ApiController
{
    public function __invoke(
        Project $project,
        Meeting $meeting,
        MeetingZoomTokensRequest $request,
        MeetingTokenService $tokenService,
        MeetingPolicy $meetingPolicy
    ): JsonResponse {
        $data = $request->toDto();
        $currentUser = $this->authenticatedUser();

        $response = $meetingPolicy->generateToken($currentUser, $project, $meeting, $data->action);
        if (! $response->allowed()) {
            abort(Response::HTTP_FORBIDDEN, $response->message());
        }

        $tokens = $tokenService->generateTokens($project, $meeting, $currentUser, $data->action);

        return $this->respondWithData($tokens, Response::HTTP_OK);
    }
}
