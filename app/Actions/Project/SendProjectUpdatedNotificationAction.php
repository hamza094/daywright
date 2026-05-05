<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Actions\NotificationAction;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectUpdated;

final class SendProjectUpdatedNotificationAction
{
    public function execute(Project $project, User $actor): void
    {
        if ($project->activeMembers->isEmpty()) {
            return;
        }

        NotificationAction::send(
            new ProjectUpdated(
                $project->name,
                $project->slug,
                $actor->getNotifierData()
            ),
            $project,
            $actor
        );
    }
}
