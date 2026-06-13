<?php

declare(strict_types=1);

namespace Tests\Support\Meeting;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;

class MeetingTestHelper
{
    public static function createMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
        ], $overrides));
    }

    public static function createActiveMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
            'sync_status' => MeetingSyncStatus::Active->value,
        ], $overrides));
    }

    public static function createFailedMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
            'sync_status' => MeetingSyncStatus::Failed->value,
            'sync_error' => 'Test error',
        ], $overrides));
    }

    public static function createPendingMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
            'sync_status' => MeetingSyncStatus::Pending->value,
        ], $overrides));
    }

    public static function createDeletingMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
            'sync_status' => MeetingSyncStatus::Deleting->value,
        ], $overrides));
    }

    public static function createDeletedMeeting(Project $project, User $user, array $overrides = []): Meeting
    {
        return Meeting::factory()->for($project)->create(array_merge([
            'user_id' => $user->id,
            'sync_status' => MeetingSyncStatus::Deleted->value,
        ], $overrides));
    }

    public static function assertMeetingStatus(Meeting $meeting, string $expectedStatus): void
    {
        \PHPUnit\Framework\assertEquals($expectedStatus, $meeting->status, 'Meeting status should match expected value');
    }

    public static function assertMeetingSyncStatus(Meeting $meeting, MeetingSyncStatus $expectedStatus): void
    {
        \PHPUnit\Framework\assertEquals($expectedStatus, $meeting->sync_status, 'Meeting sync status should match expected value');
    }
}
