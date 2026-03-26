<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Enums\TaskStatus as TaskStatusEnum;
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

    private function createTaskStatuses(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();

        $statuses = [
            TaskStatusEnum::PENDING => 'Pending',
            TaskStatusEnum::IN_PROGRESS => 'In Progress',
            TaskStatusEnum::UNDER_REVIEW => 'Under Review',
            TaskStatusEnum::COMPLETED => 'Completed',
            TaskStatusEnum::CANCELLED => 'Cancelled',
        ];

        foreach ($statuses as $id => $label) {
            TaskStatus::factory()->create([
                'id' => $id,
                'label' => $label,
                'color' => '#000000',
                'user_id' => $owner->id,
            ]);
        }
    }
}
