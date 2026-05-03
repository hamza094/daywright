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

        $meetingsData = $meetingService->getMeetingsData($project, $isPrevious);

        return response()->json([
            'success' => true,
            'message' => $meetingsData['message'],
            'meetingsData' => $meetingsData['meetingsData'],
        ], 200);
    }

    public function show(Project $project, Meeting $meeting): JsonResponse
    {
        $this->authorize('access', $project);
        $meeting->load(['user']);

        return response()->json(['success' => true, 'data' => new MeetingResource($meeting)], 200);
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

        return response()->json([
            'message' => 'Meeting Created Successfully',
            'meeting' => new MeetingResource($projectMeeting),
        ], 201);
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

        return response()->json([
            'message' => 'Meeting Updated Successfully',
            'meeting' => new MeetingResource($meeting),
        ], 200);
    }

    public function destroy(Zoom $zoom, Project $project, Meeting $meeting, MeetingService $meetingService): JsonResponse
    {
        $this->authorize('manage', $project);

        $meetingService->deleteProjectMeeting($meeting, $this->authenticatedUser(), $zoom);

        return response()->json([
            'message' => 'Meeting Deleted Successfully',
        ], 200);
    }
}
