<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Actions\CreateZoomJwtAction;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Enums\Meeting\MeetingTokenAction;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Policies\MeetingPolicy;
use App\Policies\ProjectsPolicy;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class MeetingTokenService
{
    private const int ROLE_PARTICIPANT = 0;

    private const int ROLE_HOST = 1;

    public function __construct(
        private CreateZoomJwtAction $jwtAction,
        private Zoom $zoom,
        private MeetingPolicy $meetingPolicy,
        private ProjectsPolicy $projectsPolicy,
    ) {}

    /**
     * @return array{jwt_token: string, zak_token: string|null}
     */
    public function generateTokens(
        Project $project,
        Meeting $meeting,
        User $currentUser,
        MeetingTokenAction $action
    ): array {
        $this->validateMeetingStatus($meeting);

        $role = $this->computeRole($project, $meeting, $currentUser, $action);

        $jwtToken = $this->generateJwtToken($meeting, $role);

        $zakToken = $this->generateZakToken($action, $currentUser);

        return [
            'jwt_token' => $jwtToken,
            'zak_token' => $zakToken,
        ];
    }

    private function validateMeetingStatus(Meeting $meeting): void
    {
        if ($meeting->sync_status !== MeetingSyncStatus::Active) {
            throw new HttpException(403, 'Meeting is not synced or active');
        }
    }

    private function computeRole(
        Project $project,
        Meeting $meeting,
        User $currentUser,
        MeetingTokenAction $action
    ): int {
        return match ($action) {
            MeetingTokenAction::Start => $this->computeStartRole($project, $meeting, $currentUser),
            MeetingTokenAction::Join => $this->computeJoinRole($project, $currentUser),
        };
    }

    private function computeStartRole(Project $project, Meeting $meeting, User $currentUser): int
    {
        if (! $this->meetingPolicy->canStartMeeting($currentUser, $project, $meeting)) {
            throw new HttpException(403, 'Only meeting owner or project owner can start the meeting');
        }

        return self::ROLE_HOST;
    }

    private function computeJoinRole(Project $project, User $currentUser): int
    {
        $accessResponse = $this->projectsPolicy->access($currentUser, $project);
        if (! $accessResponse->allowed()) {
            throw new HttpException(403, 'Only project owner or active project members can join the meeting');
        }

        return self::ROLE_PARTICIPANT;
    }

    private function generateJwtToken(Meeting $meeting, int $role): string
    {
        $meetingNumber = $meeting->meeting_id;

        return $this->jwtAction->execute($meetingNumber, $role);
    }

    private function generateZakToken(MeetingTokenAction $action, User $currentUser): ?string
    {
        if ($action === MeetingTokenAction::Start) {
            return $this->zoom->getZakToken($currentUser);
        }

        return null;
    }
}
