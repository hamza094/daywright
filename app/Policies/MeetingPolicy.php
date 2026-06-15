<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Meeting\MeetingTokenAction;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private ProjectsPolicy $projectsPolicy,
    ) {}

    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function generateToken(User $user, Project $project, Meeting $meeting, MeetingTokenAction $action): Response
    {
        // Check project access using ProjectsPolicy
        $accessResponse = $this->projectsPolicy->access($user, $project);

        if (! $accessResponse->allowed()) {
            return $accessResponse;
        }

        // Action-specific authorization
        return match ($action) {
            MeetingTokenAction::Start => $this->authorizeStart($user, $project, $meeting),
            MeetingTokenAction::Join => $this->authorizeJoin($user, $project),
        };
    }

    public function canStartMeeting(User $user, Project $project, Meeting $meeting): bool
    {
        return $meeting->user_id === $user->id || $project->user_id === $user->id;
    }

    private function authorizeStart(User $user, Project $project, Meeting $meeting): Response
    {
        if (! $this->canStartMeeting($user, $project, $meeting)) {
            return Response::deny('Only meeting owner or project owner can start the meeting');
        }

        return Response::allow();
    }

    private function authorizeJoin(User $user, Project $project): Response
    {
        // Already checked project access in generateToken, so just allow
        return Response::allow();
    }
}
