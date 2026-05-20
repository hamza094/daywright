<?php

declare(strict_types=1);

namespace App\Services\Project;

use Illuminate\Database\Eloquent\Builder;

class ProjectActivityQueryFilter
{
    public function filterActivities(Builder $query, ?string $filterType = null, ?int $actorId = null): Builder
    {
        return match ($filterType) {
            'specifics' => $this->filterActivityByProjectSpecified($query),
            'tasks' => $this->filterActivityByTasks($query),
            'members' => $this->filterActivityByMembers($query),
            'mine' => $actorId === null ? $query->whereRaw('0 = 1') : $query->where('user_id', $actorId),
            default => $query,
        };
    }

    private function filterActivityByTasks(Builder $query): Builder
    {
        return $query->where('description', 'like', '%_task%');
    }

    private function filterActivityByProjectSpecified(Builder $query): Builder
    {
        return $query->where('description', 'like', '%_project%');
    }

    private function filterActivityByMembers(Builder $query): Builder
    {
        $types = [
            'invitation_sent',
            'invitation_accepted',
            'member_removed',
        ];

        return $query->whereIn('description', $types);
    }
}
