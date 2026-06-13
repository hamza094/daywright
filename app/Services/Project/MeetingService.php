<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Actions\Meetings\CreateProjectMeeting;
use App\Actions\Meetings\DeleteProjectMeeting;
use App\Actions\Meetings\UpdateProjectMeeting;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class MeetingService
{
    private const array MEETING_RESOURCE_RELATIONS = ['project', 'user'];

    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function __construct(
        private readonly CreateProjectMeeting $createProjectMeeting,
        private readonly UpdateProjectMeeting $updateProjectMeeting,
        private readonly DeleteProjectMeeting $deleteProjectMeeting,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Meeting>
     */
    public function getMeetingsData(Project $project, bool $isPrevious, int $perPage = 3, ?int $page = null): LengthAwarePaginator
    {
        $meetingsQuery = $project->meetings()
            ->with(self::MEETING_RESOURCE_RELATIONS)
            ->synced();

        $meetingsQuery->when($isPrevious, fn ($query) => $query->previous(), fn ($query) => $query->scheduled());

        return $meetingsQuery->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMeetingForProject(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->loadForResponse(
            $this->createProjectMeeting->handle($project, $user, $validated, $zoom)
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProjectMeeting(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->loadForResponse(
            $this->updateProjectMeeting->handle($meeting, $user, $validated, $zoom)
        );
    }

    public function loadForResponse(Meeting $meeting): Meeting
    {
        $meeting->loadMissing(self::MEETING_RESOURCE_RELATIONS);

        return $meeting;
    }

    public function deleteProjectMeeting(Meeting $meeting, User $user, Zoom $zoom): void
    {
        $this->deleteProjectMeeting->handle($meeting, $user, $zoom);
    }
}
