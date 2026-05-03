<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectRepository
{
    /**
     * Filter project activities based on the request parameters.
     *
     * @param  Collection  $activities  The collection of activities to filter.
     * @return Collection The filtered collection of activities.
     */
    public function filterActivities(Collection $activities, ?string $filterType = null): Collection
    {
        $filters = [
            'specifics' => 'filterActivityByProjectSpecified',
            'tasks' => 'filterActivityByTasks',
            'members' => 'filterActivityByMembers',
            'mine' => 'filterActivityByAuthUser',
        ];

        if ($filterType !== null && array_key_exists($filterType, $filters)) {
            $method = $filters[$filterType];
            $activities = $this->$method($activities);
        }

        return $activities;
    }

    /**
     * Filter activities by authenticated user.
     *
     * @param  Collection  $activities
     */
    protected function filterActivityByAuthUser($activities): Collection
    {
        return $activities->where('user_id', auth()->id());
    }

    /**
     * Filter activities by project-related tasks.
     *
     * @param  Collection  $activities
     */
    protected function filterActivityByTasks($activities): Collection
    {
        return $activities->filter(fn ($activity): bool => str_contains((string) $activity['description'], '_task'));
    }

    /**
     * Filter activities by project-specified.
     *
     * @param  Collection  $activities
     */
    protected function filterActivityByProjectSpecified($activities): Collection
    {
        return $activities->filter(fn ($activity): bool => str_contains((string) $activity['description'], '_project'));
    }

    /**
     * Filter activities by project-member releated.
     *
     * @param  Collection  $activities
     */
    protected function filterActivityByMembers($activities): Collection
    {
        $types = [
            'invitation_sent',
            'invitation_accepted',
            'member_removed',
        ];

        return $activities->filter(fn ($activity): bool => in_array($activity['description'], $types));

    }
}
