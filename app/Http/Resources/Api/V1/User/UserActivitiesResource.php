<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\Project\ProjectSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Override;

/**
 * @mixin \App\Models\Activity
 */
class UserActivitiesResource extends JsonResource
{
    private const string DELETED = '(deleted)';

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request)
    {
        return [
            /**
             * Activity identifier.
             *
             * @example 91
             */
            'id' => $this->id,
            /**
             * Human-readable activity summary.
             *
             * @example Task 'Finalize copy..' status updated in Website Refresh
             */
            'description' => $description = (method_exists($this, $this->description) ? $this->{$this->description}() : $this->description),
            /**
             * Related project summary, including soft-deleted projects when the activity still references them.
             */
            'project' => $this->whenLoaded('project') && $this->project ? new ProjectSummaryResource($this->project) : null,
            /**
             * Activity timestamp in UTC ISO 8601 format.
             *
             * @example 2025-08-15T10:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
            /**
             * Minimal reference to the affected subject.
             *
             * @example {"type":"Task","id":22}
             */
            'subject' => $this->getSubjectDetails(),
            /**
             * UI color hint derived from the activity type.
             *
             * @example yellow
             */
            'color' => $this->colorFromDescription($description),
            /**
             * Identifier of the user who triggered the activity.
             *
             * @example 5
             */
            'user_id' => $this->user_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getSubjectDetails(): array
    {
        return [
            'type' => class_basename($this->subject_type),
            'id' => optional($this->subject)->id,
        ];
    }

    protected function created_project(): string
    {
        return $this->project
            ? "Project {$this->project->name} created"
            : 'Project '.self::DELETED.' created';
    }

    protected function updated_project(): string
    {
        if (! $this->project) {
            return 'Project '.self::DELETED.' updated';
        }

        if (empty($this->changes) || ! isset($this->changes['after'])) {
            return "Project {$this->project->name} updated";
        }

        $changesAfter = $this->changes['after'] ?? [];
        $updatedKey = key($changesAfter);

        if ($updatedKey === 'stage_id') {
            return "Project {$this->project->name} stage changed";
        }

        if ($updatedKey === 'deleted_at') {
            return "Project {$this->project->name} was archived";
        }

        return "Project {$this->project->name} ".($updatedKey ?? '').' updated';
    }

    protected function deleted_project(): string
    {
        return $this->project
            ? "Project {$this->project->name} archived"
            : 'Project '.self::DELETED.' archived';
    }

    protected function restored_project(): string
    {
        return $this->project
            ? "Project {$this->project->name} restored"
            : 'Project '.self::DELETED.' restored';
    }

    protected function created_task(): string
    {
        if ($this->subject && property_exists($this->subject, 'title') && $this->subject->title) {
            $projectName = $this->project ? $this->project->name : self::DELETED;

            return 'Task '.Str::limit($this->subject->title, 12, '..').' added in '.$projectName;
        }
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Task added in {$projectName}";
    }

    protected function updated_task(): string
    {
        $task = $this->subject;
        $updatedKey = isset($this->changes['after']) ? key($this->changes['after']) : null;
        $taskTitle = $task && property_exists($task, 'title') && $task->title ? Str::limit($task->title, 12, '..') : '(deleted)';
        $projectName = $this->project ? $this->project->name : self::DELETED;

        if ($updatedKey === 'status_id') {
            return "Task '$taskTitle' status updated in {$projectName}";
        }

        return "Task '$taskTitle' ".($updatedKey ? Str::headline($updatedKey) : '')." updated in project {$projectName}";
    }

    protected function deleted_task(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;
        if (! $this->subject) {
            return "One Task has been removed from the project {$projectName}";
        }
        $taskTitle = property_exists($this->subject, 'title') ? Str::limit($this->subject->title, 17, '...') : self::DELETED;

        return "Task '$taskTitle' archived from the project {$projectName}";
    }

    protected function created_message(): string
    {
        $status = ($this->subject && property_exists($this->subject, 'delivered_at') && $this->subject->delivered_at === null) ? 'scheduled' : 'sent';
        $message = $this->subject && property_exists($this->subject, 'message') ? Str::limit($this->subject->message, 12, '..') : '';

        return 'Message '.$message.' '.$status;
    }

    protected function sent_invitation_member(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Project {$projectName} invitation sent to a member";
    }

    protected function invitation_accepted(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Project {$projectName} invitation accepted";
    }

    protected function accept_invitation_member(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Project {$projectName} invitation accepted";
    }

    protected function remove_project_member(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Project {$projectName} member removed";
    }

    protected function created_meeting(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;
        $topic = $this->subject && property_exists($this->subject, 'topic') ? $this->subject->topic : '';

        return "Meeting {$topic} created in project {$projectName}";
    }

    protected function updated_meeting(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;
        $topic = $this->subject && property_exists($this->subject, 'topic') ? $this->subject->topic : '';

        return "Meeting {$topic} updated in project {$projectName}";
    }

    protected function deleted_meeting(): string
    {
        $projectName = $this->project ? $this->project->name : self::DELETED;

        return "Meeting deleted from project {$projectName}";
    }

    protected function colorFromDescription(string $desc): string
    {
        $color = 'green';

        if (Str::startsWith($desc, 'Project')) {
            $color = 'purple';
        } elseif (Str::startsWith($desc, 'Task')) {
            $color = 'yellow';
        } elseif (Str::startsWith($desc, 'Meeting')) {
            $color = 'red';
        }

        return $color;
    }
}
