<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Meeting;
use App\Models\Project;
use App\Policies\MeetingPolicy;
use App\Services\Zoom\MeetingTokenService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @operationId generateMeetingStartTokens
 *
 * @tags Meetings
 */
class MeetingZoomStartTokensController extends ApiController
{
    public function __invoke(
        Project $project,
        Meeting $meeting,
        MeetingTokenService $tokenService,
        MeetingPolicy $meetingPolicy
    ): JsonResponse {
        $currentUser = $this->authenticatedUser();

        $response = $meetingPolicy->generateToken($currentUser, $project, $meeting, \App\Enums\Meeting\MeetingTokenAction::Start);
        if (! $response->allowed()) {
            abort(Response::HTTP_FORBIDDEN, $response->message());
        }

        $tokens = $tokenService->generateTokens($project, $meeting, $currentUser, \App\Enums\Meeting\MeetingTokenAction::Start);

        return $this->respondWithData($tokens, Response::HTTP_OK);
    }
}
