<?php

declare(strict_types=1);

namespace App\Services\Project;

use Illuminate\Support\Collection;

class ProjectActivityCollectionFilter
{
    public function filterActivities(Collection $activities, ?string $filterType = null, ?int $actorId = null): Collection
    {
        return match ($filterType) {
            'specifics' => $this->filterActivityByProjectSpecified($activities),
            'tasks' => $this->filterActivityByTasks($activities),
            'members' => $this->filterActivityByMembers($activities),
            'mine' => $actorId === null ? collect() : $activities->where('user_id', $actorId),
            default => $activities,
        };
    }

    private function filterActivityByTasks(Collection $activities): Collection
    {
        return $activities->filter(fn ($activity): bool => str_contains((string) $activity['description'], '_task'));
    }

    private function filterActivityByProjectSpecified(Collection $activities): Collection
    {
        return $activities->filter(fn ($activity): bool => str_contains((string) $activity['description'], '_project'));
    }

    private function filterActivityByMembers(Collection $activities): Collection
    {
        $types = [
            'invitation_sent',
            'invitation_accepted',
            'member_removed',
        ];

        return $activities->filter(fn ($activity): bool => in_array($activity['description'], $types, true));
    }
}
