<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Enums\TaskSystemStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;

trait FixtureHelpers
{
    private function createMeeting(User $user, Project $project): void
    {
        Meeting::factory()->for($user)->for($project)->create();
    }

    private function createApiToken(User $user, ?Project $project = null): void
    {
        $user->createToken('primary-token');
    }

    private function createApiTokens(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user->createToken("token-{$i}");
        }
    }

    private function createTaskStatuses(?User $owner = null): void
    {
        $userId = $owner?->id;

        $statuses = [
            TaskSystemStatus::Pending->value => 'Pending',
            TaskSystemStatus::InProgress->value => 'In Progress',
            TaskSystemStatus::UnderReview->value => 'Under Review',
            TaskSystemStatus::Completed->value => 'Completed',
            TaskSystemStatus::Cancelled->value => 'Cancelled',
        ];

        foreach ($statuses as $id => $label) {
            TaskStatus::factory()->create([
                'id' => $id,
                'label' => $label,
                'color' => '#000000',
                'user_id' => $userId,
            ]);
        }
    }
}
