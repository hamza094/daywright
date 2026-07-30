<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskSystemStatus;
use App\QueryBuilder\TaskQueryBuilder;
use App\Traits\HasStateMachine;
use App\Traits\RecordActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @mixin \App\QueryBuilder\TaskQueryBuilder
 */
class Task extends Model
{
    use HasFactory, HasStateMachine, RecordActivity, SoftDeletes;

    protected $guarded = [];

    // protected $touches=['project'];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    /**
     * The events that should be recorded.
     *
     * @var array<string>
     */
    protected static $recordableEvents = ['created', 'updated', 'deleted'];

    /**
     * Create a new Eloquent query builder for the model.
     */
    #[Override]
    public function newEloquentBuilder($query): TaskQueryBuilder
    {
        return new TaskQueryBuilder($query);
    }

    /**
     * Get project associated to the task.
     *
     * @return BelongsTo<Project, Task>
     *
     * @phpstan-return BelongsTo<Project, static>
     */
    public function project(): BelongsTo
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsTo(Project::class);
    }

    /**
     * Get user who created task.
     *
     * @return BelongsTo<User, Task>
     *
     * @phpstan-return BelongsTo<User, static>
     */
    public function owner(): BelongsTo
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the task assignees.
     *
     * @return BelongsToMany<User, Task, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<User, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function assignee(): BelongsToMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<User, Task, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<User, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function assigneeBasic(): BelongsToMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(User::class)
            ->select(['users.id', 'users.name']);
    }

    /**
     * Get status associated to the task.
     *
     * @return BelongsTo<TaskStatus, Task>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function state(): string
    {
        return $this->trashed() ? 'trashed' : 'active';
    }

    #[Override]
    protected static function booted(): void
    {
        static::creating(function ($task): void {
            if (! $task->status_id) {
                $task->status_id = TaskSystemStatus::Pending->value;
            }
        });

        static::forceDeleting(function ($task): void {
            $task->activities()->delete();
        });

    }

    /**
     * Define the valid state transitions for the Task model.
     *
     * @return array<string, list<int>>
     */
    protected function validTransitions(): array
    {
        return [
            TaskSystemStatus::Pending->value => [
                TaskSystemStatus::InProgress->value,
                TaskSystemStatus::Cancelled->value,
            ],
            TaskSystemStatus::InProgress->value => [
                TaskSystemStatus::UnderReview->value,
                TaskSystemStatus::Pending->value,
                TaskSystemStatus::Cancelled->value,
            ],
            TaskSystemStatus::UnderReview->value => [
                TaskSystemStatus::Completed->value,
                TaskSystemStatus::InProgress->value,
                TaskSystemStatus::Cancelled->value,
            ],
            TaskSystemStatus::Completed->value => [],
            TaskSystemStatus::Cancelled->value => [],
        ];
    }
}
