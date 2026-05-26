<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\User\UserSummaryResource;
use App\Models\Stage;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Override;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $description = method_exists($this, $this->description)
            ? $this->{$this->description}()
            : $this->description;

        return [
            'description' => $description,
            'time' => $this->created_at?->toIso8601String(),
            'subject' => $this->getSubjectDetails(),
            'user' => $this->whenLoaded(
                'user',
                fn (): UserSummaryResource => new UserSummaryResource($this->user),
            ),
            'affected_users' => $this->when(! empty($this->affected_users), $this->loadAffectedUsers()),
        ];
    }

    protected function getSubjectDetails(): array
    {
        return [
            'type' => class_basename($this->subject_type),
            'id' => optional($this->subject)->id,
        ];
    }

    protected function created_project(): string
    {
        return 'New project created';
    }

    protected function updated_project(): string
    {
        $changes = Arr::get($this->changes, 'after', []);
        $updatedKey = key($changes);

        if (! $updatedKey) {
            return 'No changes detected';
        }

        if ($updatedKey === 'stage_id') {
            $stages = Cache::remember('stages_map', 300, fn () => Stage::pluck('name', 'id')->toArray());

            $newStage = $stages[$changes['stage_id']] ?? 'Unknown';

            return "Project stage changed to '$newStage'";
        }

        return $updatedKey === 'deleted_at'
            ? 'Project has been restored'
            : 'Project '.Str::headline($updatedKey).' updated';
    }

    protected function deleted_project(): string
    {
        return 'Project archived';
    }

    protected function restored_project(): string
    {
        return 'Project has been restored';
    }

    protected function created_task(): string
    {
        if (! $this->subject) {
            return 'Task not found';
        }
        $title = Str::limit($this->subject->title, 10, '...');

        return 'Task "'.$title.'" added';
    }

    protected function updated_task(): string
    {
        if (! $this->subject) {
            return 'Task updated';
        }

        $taskTitle = Str::limit($this->subject->title, 17, '...');

        $changes = Arr::get($this->changes, 'after', []);

        $updatedKey = key($changes);

        if (! $updatedKey) {
            return 'No changes detected';
        }

        if ($updatedKey === 'status_id') {
            $statuses = Cache::remember('task_statuses_map', 300, fn () => TaskStatus::pluck('label', 'id')->toArray());

            $newStatus = $statuses[$changes['status_id']] ?? 'Unknown';

            return "Task '$taskTitle' status changed to '$newStatus'";
        }

        return $updatedKey === 'deleted_at'
            ? "Task '$taskTitle' has been restored"
            : "Task '$taskTitle' ".Str::headline($updatedKey).' updated';

    }

    protected function deleted_task(): string
    {
        if (! $this->subject) {
            return 'One Task has been removed from the project';
        }
        $taskTitle = Str::limit($this->subject->title, 17, '...');

        return "Task '$taskTitle' archived from the project";
    }

    protected function created_message(): string
    {
        if (! $this->subject) {
            return 'Message status unknown';
        }

        $status = $this->subject->delivered_at ? 'sent' : 'scheduled';
        $messageContent = Str::limit(trim($this->subject->message ?? ''), 17, '..');

        return "Message '$messageContent' $status";
    }

    protected function invitation_sent(): string
    {
        return 'Project invitation sent to affected members.';
    }

    protected function invitation_accepted(): string
    {
        return 'Project invitation accepted by affected members.';
    }

    protected function member_removed(): string
    {
        return 'Project members removed from the project';
    }

    protected function created_meeting(): string
    {
        return 'Meeting '.($this->subject?->topic ?? '').' created';
    }

    protected function updated_meeting(): string
    {
        return 'Meeting '.($this->subject?->topic ?? '').' updated';
    }

    protected function deleted_meeting(): string
    {
        return 'Meeting deleted from the project';
    }

    protected function loadAffectedUsers(): array
    {
        if (empty($this->affected_users) || ! is_array($this->affected_users)) {
            return [];
        }

        $userIds = $this->affected_users;

        // Fetch existing users, only selecting necessary columns
        $users = User::whereIn('id', $userIds)
            ->select(['id', 'uuid', 'name'])
            ->get()
            ->keyBy('id')
            ->toArray(); // Convert collection to array for faster lookups

        return array_map(fn ($userId) => array_key_exists($userId, $users)
            ? $users[$userId] // Existing user data
            : ['id' => $userId, 'uuid' => null, 'name' => 'Deleted User'], // Deleted user fallback
            $userIds
        );
    }
}
