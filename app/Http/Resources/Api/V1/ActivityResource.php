<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\User\UserSummaryResource;
use App\Models\Activity;
use App\Models\Stage;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Override;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    private const string UPDATED_SUFFIX = ' updated';

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $descriptionKey = (string) data_get($this->resource, 'description', '');
        $description = method_exists($this, $descriptionKey)
            ? $this->{$descriptionKey}()
            : $descriptionKey;

        return [
            /** @var string */
            'description' => $description,
            /**
             * Activity timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2025-07-01T09:00:00+00:00
             */
            'time' => $this->created_at?->toIso8601String(),
            /** @var array{type: string, id: int|null} */
            'subject' => $this->getSubjectDetails(),
            'user' => $this->whenLoaded(
                'user',
                fn (): UserSummaryResource => new UserSummaryResource($this->user),
            ),
            /** @var array<int, array{id: int, uuid: string|null, name: string}> */
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
            : 'Project '.Str::headline($updatedKey).self::UPDATED_SUFFIX;
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
        $title = Str::limit((string) data_get($this->subject, 'title', ''), 10, '...');

        return 'Task "'.$title.'" added';
    }

    protected function updated_task(): string
    {
        if (! $this->subject) {
            return 'Task updated';
        }

        $taskTitle = Str::limit((string) data_get($this->subject, 'title', ''), 17, '...');

        $changes = Arr::get($this->changes, 'after', []);

        $updatedKey = key($changes);
        $description = 'No changes detected';

        if ($updatedKey === 'status_id') {
            $statuses = Cache::remember('task_statuses_map', 300, fn () => TaskStatus::pluck('label', 'id')->toArray());

            $newStatus = $statuses[$changes['status_id']] ?? 'Unknown';

            $description = "Task '$taskTitle' status changed to '$newStatus'";
        } elseif (is_string($updatedKey) && $updatedKey !== '') {
            $description = "Task '$taskTitle' ".Str::headline($updatedKey).self::UPDATED_SUFFIX;

            if ($updatedKey === 'deleted_at') {
                $description = "Task '$taskTitle' has been restored";
            }
        }

        return $description;
    }

    protected function deleted_task(): string
    {
        if (! $this->subject) {
            return 'One Task has been removed from the project';
        }
        $taskTitle = Str::limit((string) data_get($this->subject, 'title', ''), 17, '...');

        return "Task '$taskTitle' archived from the project";
    }

    protected function created_message(): string
    {
        if (! $this->subject) {
            return 'Message status unknown';
        }

        $status = data_get($this->subject, 'delivered_at') ? 'sent' : 'scheduled';
        $messageContent = Str::limit(trim((string) data_get($this->subject, 'message', '')), 17, '..');

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
        return 'Meeting '.data_get($this->subject, 'topic', '').' created';
    }

    protected function updated_meeting(): string
    {
        return 'Meeting '.data_get($this->subject, 'topic', '').self::UPDATED_SUFFIX;
    }

    protected function deleted_meeting(): string
    {
        return 'Meeting deleted from the project';
    }

    protected function loadAffectedUsers(): array
    {
        $affectedUsers = data_get($this->resource, 'affected_users');

        if (! is_array($affectedUsers) || $affectedUsers === []) {
            return [];
        }

        $userIds = $affectedUsers;

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
