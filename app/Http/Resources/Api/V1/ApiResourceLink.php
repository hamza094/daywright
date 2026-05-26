<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

final class ApiResourceLink
{
    public static function project(Project $project): string
    {
        return route('api.v1.projects.show', ['project' => $project], false);
    }

    public static function task(Task $task): string
    {
        // NOTE: callers should eager-load the `project` relation to avoid N+1 queries.
        // This helper will load the relation if missing, which may cause a query per item.
        $task->loadMissing('project');

        return route('api.v1.tasks.show', [
            'project' => $task->project,
            'task' => $task,
        ], false);
    }

    public static function user(User $user): string
    {
        return route('api.v1.users.show', ['user' => $user], false);
    }

    public static function meeting(Meeting $meeting): string
    {
        // NOTE: callers should eager-load the `project` relation to avoid N+1 queries.
        // This helper will load the relation if missing, which may cause a query per item.
        $meeting->loadMissing('project');

        return route('api.v1.meetings.show', [
            'project' => $meeting->project,
            'meeting' => $meeting,
        ], false);
    }
}
