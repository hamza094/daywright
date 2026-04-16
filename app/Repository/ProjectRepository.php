<?php

declare(strict_types=1);

namespace App\Repository;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectRepository
{
    /**
     * Filter project activities based on the request parameters.
     *
     * @param  HasMany|MorphMany  $activities  The activities relation query to filter.
     * @return HasMany|MorphMany The filtered activities relation query.
     */
    public function filterActivities(HasMany|MorphMany $activities): HasMany|MorphMany
    {
        $filters = [
            'specifics' => 'filterActivityByProjectSpecified',
            'tasks' => 'filterActivityByTasks',
            'members' => 'filterActivityByMembers',
            'mine' => 'filterActivityByAuthUser',
        ];

        $filter = array_key_first(request()->only(array_keys($filters)));

        if ($filter !== null && array_key_exists($filter, $filters)) {
            $method = $filters[$filter];
            $activities = $this->$method($activities);
        }

        return $activities;
    }

    protected function filterActivityByAuthUser(HasMany|MorphMany $activities): HasMany|MorphMany
    {
        return $activities->where('user_id', auth()->id());
    }

    protected function filterActivityByTasks(HasMany|MorphMany $activities): HasMany|MorphMany
    {
        return $activities->whereIn('description', [
            'created_task',
            'updated_task',
            'deleted_task',
        ]);
    }

    protected function filterActivityByProjectSpecified(HasMany|MorphMany $activities): HasMany|MorphMany
    {
        return $activities->whereIn('description', [
            'created_project',
            'updated_project',
            'deleted_project',
            'restored_project',
        ]);
    }

    protected function filterActivityByMembers(HasMany|MorphMany $activities): HasMany|MorphMany
    {
        $types = [
            'invitation_sent',
            'invitation_accepted',
            'member_removed',
        ];

        return $activities->whereIn('description', $types);

    }
}
