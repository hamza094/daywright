<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\Admin\User\AdminUserSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonSerializable;
use Override;

class ActivitiesResource extends JsonResource
{
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
            'description' => $this->{$this->description}(),
            'project' => $this->whenLoaded('project') ? new ProjectsResource($this->project) : null,
            'time' => $this->created_at->diffForHumans(),
            'subject_id' => $this->subject_type === \App\Models\Task::class ? ($this->subject ? $this->subject->id : null) : $this->subject_type,
            'user' => new AdminUserSummaryResource($this->whenLoaded('user')),
        ];
    }

    protected function created_project(): string
    {
        return 'Created Project';
    }

    protected function updated_project(): string
    {
        $updatedKey = key($this->changes['after']);

        $status = '';

        if ($updatedKey === 'stage_id') {
            $status = 'Updated Project stage in';
        } elseif ($updatedKey === 'deleted_at') {
            $status = 'Made live project';
        } else {
            $status = 'Updated Project '.$updatedKey.' in';
        }

        return $status;
    }

    protected function deleted_project(): string
    {
        return 'Abandoned Project';
    }

    protected function restored_project(): string
    {
        return 'Restores back Project';
    }

    protected function created_task(): string
    {
        if ($this->subject && $this->subject->title) {
            return 'Added new task  '.Str::limit($this->subject->title, 7, '..').' '.'in';
        }

        return 'Added new Task in';
    }

    protected function updated_task(): string
    {
        $task = $this->subject;
        $updatedKey = key($this->changes['after']);
        $taskName = Str::limit($task->title, 17, '..');

        if ($updatedKey === 'completed') {
            return "Updated Task '$taskName' status in";
        }

        return "Updated Task '$taskName' title in";
    }

    protected function deleted_task(): string
    {
        return 'Archived Task archived in';
    }

    protected function updated_taskstatus(): string
    {
        $label = $this->subject->label;

        return "Updated Task Status with label '$label'";
    }

    protected function created_taskstatus(): string
    {
        return 'Created new Task Status';
    }

    protected function deleted_taskstatus(): string
    {
        return 'Deleted Task status';
    }

    protected function updated_stage(): string
    {
        $name = $this->subject->name;

        return "Updated Stage with name '$name'";
    }

    protected function created_stage(): string
    {
        return 'Created new Stage';
    }

    protected function deleted_stage(): string
    {
        return 'Deleted Stage';
    }

    protected function created_message(): string
    {
        $status = $this->subject->delivered_at === null ? 'scheduled' : 'sent';

        return $status.Str::limit($this->subject->message, 17, '..').' '.'Message in';
    }

    protected function sent_invitation_member(): string
    {
        return 'Sent invitation '.$this->info.''.'to Project';
    }

    protected function accept_invitation_member(): string
    {
        return 'Invitation accepted by '.$this->info.' of Project';
    }

    protected function remove_project_member(): string
    {
        return 'Member '.$this->info.' '.' removed from project';
    }
}
