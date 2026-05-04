<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Enums\Subscription\PlanLimitType;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Api\V1\Subscription\PlanLimitService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    public function getMeetingsData(Project $project, bool $isPrevious): LengthAwarePaginator
    {
        $meetingsQuery = $project->meetings()->with('user');

        $meetingsQuery->when($isPrevious, fn ($query) => $query->previous(), fn ($query) => $query->scheduled());

        return $meetingsQuery->paginate(3);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMeetingForProject(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        $this->planLimitService->assertWithinLimit(PlanLimitType::CreatedMeetings, $user);

        $meeting = $zoom->createMeeting($validated, $user);

        /** @var Meeting $projectMeeting */
        $projectMeeting = $this->planLimitService->executeWithinAccountLimit(
            PlanLimitType::CreatedMeetings,
            $user,
            function (User $lockedUser) use ($meeting, $project): Meeting {
                $meetingArray = (array) $meeting + ['user_id' => $lockedUser->id];

                return $project->meetings()->create($meetingArray);
            }
        );

        return $projectMeeting->load('user');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProjectMeeting(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        DB::transaction(function () use ($zoom, $meeting, $validated, $user): void {
            $meeting->update($validated);
            $zoom->updateMeeting($validated, $user);
        });

        return $meeting->load('user');
    }

    public function deleteProjectMeeting(Meeting $meeting, User $user, Zoom $zoom): void
    {
        $meetingId = $meeting->meeting_id;

        DB::transaction(function () use ($zoom, $meeting, $meetingId, $user): void {
            $meeting->delete();
            $zoom->deleteMeeting($meetingId, $user);
        });
    }
}
