<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Subscription\PlanLimitType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\MeetingStoreRequest;
use App\Http\Requests\Api\V1\Zoom\MeetingUpdateRequest;
use App\Http\Resources\Api\V1\Zoom\MeetingResource;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\ExceptionService;
use App\Services\Api\V1\MeetingService;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoomMeetingController extends ApiController
{
    public function __construct(protected ExceptionService $exceptionService) {}

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

    public function store(Zoom $zoom, Project $project, MeetingStoreRequest $request, PlanLimitService $planLimitService): JsonResponse
    {
        $this->authorize('manage', $project);

        $user = $this->authenticatedUser();
        $validated = $request->validated();

        $planLimitService->assertWithinLimit(PlanLimitType::CreatedMeetings, $user);

        $meeting = $zoom->createMeeting($validated, $user);

        $projectMeeting = $planLimitService->executeWithinAccountLimit(
            PlanLimitType::CreatedMeetings,
            $user,
            function (User $lockedUser) use ($meeting, $project): Meeting {
                $meetingArray = (array) $meeting + ['user_id' => $lockedUser->id];

                return $project->meetings()->create($meetingArray);
            }
        );

        return response()->json([
            'message' => 'Meeting Created Successfully',
            'meeting' => new MeetingResource($projectMeeting),
        ], 201);
    }

    public function update(Zoom $zoom, Project $project, Meeting $meeting, MeetingUpdateRequest $request): JsonResponse
    {
        $this->authorize('manage', $project);

        $user = $this->authenticatedUser();

        \Illuminate\Support\Facades\DB::transaction(function () use ($zoom, $meeting, $request, $user): void {
            $meeting->update($request->validated());
            $zoom->updateMeeting($request->validated(), $user);
        });

        $meeting->load(['user']);

        return response()->json([
            'message' => 'Meeting Updated Successfully',
            'meeting' => new MeetingResource($meeting),
        ], 200);
    }

    public function destroy(Zoom $zoom, Project $project, Meeting $meeting): JsonResponse
    {
        $this->authorize('manage', $project);

        $meetingId = $meeting->meeting_id;
        $user = $this->authenticatedUser();

        \Illuminate\Support\Facades\DB::transaction(function () use ($zoom, $meeting, $meetingId, $user): void {
            $meeting->delete();
            $zoom->deleteMeeting($meetingId, $user);
        });

        return response()->json([
            'message' => 'Meeting Deleted Successfully',
        ], 200);
    }
}
