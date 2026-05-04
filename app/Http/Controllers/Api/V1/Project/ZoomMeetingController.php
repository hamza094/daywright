<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\MeetingStoreRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingUpdateRequest;
use App\Http\Resources\Api\V1\Zoom\MeetingResource;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Services\Api\V1\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoomMeetingController extends ApiController
{
    public function index(Project $project, Request $request, MeetingService $meetingService): JsonResponse
    {
        $this->authorize('access', $project);

        $isPrevious = ($request->query('request') === 'previous');

        $meetings = $meetingService->getMeetingsData($project, $isPrevious);

        return MeetingResource::collection($meetings)->response();
    }

    public function show(Project $project, Meeting $meeting): MeetingResource
    {
        $this->authorize('access', $project);
        $meeting->load(['user']);

        return new MeetingResource($meeting);
    }

    public function store(Zoom $zoom, Project $project, MeetingStoreRequest $request, MeetingService $meetingService): JsonResponse
    {
        $this->authorize('manage', $project);

        $projectMeeting = $meetingService->createMeetingForProject(
            $project,
            $this->authenticatedUser(),
            $request->validated(),
            $zoom,
        );

        return $this->respondCreated(new MeetingResource($projectMeeting));
    }

    public function update(Zoom $zoom, Project $project, Meeting $meeting, MeetingUpdateRequest $request, MeetingService $meetingService): JsonResponse
    {
        $this->authorize('manage', $project);

        $meeting = $meetingService->updateProjectMeeting(
            $meeting,
            $this->authenticatedUser(),
            $request->validated(),
            $zoom,
        );

        return $this->respondUpdated(new MeetingResource($meeting));
    }

    public function destroy(Zoom $zoom, Project $project, Meeting $meeting, MeetingService $meetingService): JsonResponse
    {
        $this->authorize('manage', $project);

        $meetingService->deleteProjectMeeting($meeting, $this->authenticatedUser(), $zoom);

        return $this->respondWithMessage('Meeting Deleted Successfully');
    }
}
