<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Actions\NotifyProjectMembersAction;
use App\DataTransferObjects\Notification\NotificationActorData;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectUpdated;

final class SendProjectUpdatedNotificationAction
{
    public function __construct(private readonly NotifyProjectMembersAction $notifyProjectMembersAction) {}

    public function execute(Project $project, User $actor): void
    {
        if ($project->activeMembers->isEmpty()) {
            return;
        }

        $this->notifyProjectMembersAction->execute(
            new ProjectUpdated(
                $project->name,
                $project->slug,
                NotificationActorData::fromUser($actor)
            ),
            $project,
            $actor
        );
    }
}
