<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;
use Override;

class UserActivitiesResource extends JsonResource
{
    private const string DELETED = '(deleted)';

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'description' => method_exists($this, $this->description)
              ? $this->{$this->description}()
              : $this->description,
            'project' => $this->whenLoaded('project') && $this->project ? new ProjectsResource($this->project) : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'subject' => $this->getSubjectDetails(),
            'color' => $this->color(),
            'user_id' => $this->user_id,
        ];
    }

    /*protected function getSubjectDetails(): array
    {
      if ($this->relationLoaded('subject') && $this->subject) {
        return [
          'id' => $this->subject->id ?? null,
          'type' => class_basename($this->subject),
          'name' => $this->subject->name ?? ($this->subject->title ?? null),
        ];
      }
      return [];
    }*/

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
        if ($this->subject && $this->subject->title) {
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
        $taskTitle = $task && $task->title ? Str::limit($task->title, 12, '..') : '(deleted)';
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
        $taskTitle = Str::limit($this->subject->title, 17, '...');

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

    protected function color(): string
    {
        $desc = method_exists($this, $this->description) ? $this->{$this->description}() : '';
        if (Str::startsWith($desc, 'Project')) {
            return 'purple';
        }
        if (Str::startsWith($desc, 'Task')) {
            return 'yellow';
        }
        if (Str::startsWith($desc, 'Meeting')) {
            return 'red';
        }

        return 'green';

    }
}
