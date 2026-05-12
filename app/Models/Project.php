<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectHealthStatus;
use App\Exceptions\ArchivedResourceException;
use App\QueryBuilder\ProjectQueryBuilder;
use App\Traits\RecordActivity;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Override;

/** @use HasFactory<ProjectFactory> */
class Project extends Model
{
    use HasFactory;
    use RecordActivity;
    use Sluggable;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Date/time attributes on the model.
     *
     * @var array<int,string>
     */
    protected $dates = ['created_at'];

    /**
     * Accessors to append to model's array/JSON form.
     *
     * @var array<int,string>
     */
    protected $appends = ['health_status'];

    /**
     * Project model cast properties.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'stage_updated_at' => 'datetime',
        'delivered_at' => 'datetime',
        'health_score' => 'float',
        'health_score_calculated_at' => 'datetime',
    ];

    /**
     * The events that should be recorded.
     *
     * @var array<string>
     */
    protected static $recordableEvents = ['created', 'updated', 'deleted', 'restored'];

    public static function bootRecordActivity(): void
    {
        foreach (static::recordableEvents() as $event) {
            static::$event(function ($model) use ($event): void {
                // Only record activity on soft delete, not force delete
                if ($event === 'deleted' && method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                    return;
                }
                $model->recordActivity($model->activityDescription($event), []);
            });

            if ($event === 'updated') {
                static::updating(function ($model): void {
                    $model->oldAttributes = $model->getOriginal();
                });
            }
        }
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array<string, array<string, string>>
     */
    #[Override]
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    #[Override]
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $project = parent::resolveRouteBinding($value, $field);

        if ($project !== null) {
            return $project;
        }

        if ($this->resolveSoftDeletableRouteBinding($value, $field)?->trashed()) {
            throw ArchivedResourceException::project();
        }

        return null;
    }

    #[Override]
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        $childModel = parent::resolveChildRouteBinding($childType, $value, $field);

        if ($childModel !== null || $childType !== 'task') {
            return $childModel;
        }

        $trashedChildModel = parent::resolveSoftDeletableChildRouteBinding($childType, $value, $field);

        if ($trashedChildModel instanceof Task && $trashedChildModel->trashed()) {
            throw ArchivedResourceException::task();
        }

        return null;
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    #[Override]
    public function newEloquentBuilder($query): ProjectQueryBuilder
    {
        return new ProjectQueryBuilder($query);
    }

    /**
     * Get user associated to the project.
     *
     * @return BelongsTo<User, Project>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get stage associated to the project.
     *
     * @return BelongsTo<Stage, Project>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get tasks releated to the project.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->latest();
    }

    /**
     * Get project messages.
     *
     * @return HasMany<Message>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function addTask(string $title): Task
    {
        return $this->tasks()->create([
            'title' => $title,
            'user_id' => auth()->id(),
            'status_id' => 1,
        ]);
    }

    /**
     * Add multiple tasks to the project.
     *
     * @param  array<int,array<string,mixed>>  $tasks
     * @return EloquentCollection<int,Task>
     */
    public function addTasks(array $tasks): EloquentCollection
    {
        // $this->timestamps = false;

        return $this->tasks()->createManyQuietly($tasks);
    }

    public function invite(User $user): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['active' => false],
        ]);
    }

    /**
     * Get the project members.
     *
     * @return BelongsToMany<User>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('active')->withTimestamps();
    }

    /**
     * Get project active members.
     *
     * @return BelongsToMany<User>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this
            ->members()
            ->wherePivot('active', true);
    }

    /**
     * Get the project active members.
     *
     * @return BelongsToMany<User>
     */
    public function asignees(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'project_members')
            ->wherePivot('active', true)
            ->select(['users.id', 'users.name', 'users.email']);
    }

    /**
     * Get chat conversations releated to the project.
     *
     * @return HasMany<Conversation>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function tasksReachedItsLimit(): bool
    {
        $this->loadCount('tasks');

        return $this->tasks_count === (int) config('app.project.taskLimit');
    }

    /**
     * Derived health status label from persisted health_score.
     */
    public function getHealthStatusAttribute(): string
    {
        $score = (float) ($this->health_score ?? 0.0);

        return match (true) {
            $score >= 75.0 => ProjectHealthStatus::HOT->value,
            $score >= 45.0 => ProjectHealthStatus::WARM->value,
            default => ProjectHealthStatus::COLD->value,
        };
    }

    /**
     * Scope for ordering by health_score.
     */
    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOrderByHealthScore(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('health_score', $direction);
    }

    /**
     * Get all scheduled messages releated to project
     *
     * @return Collection<int, Message>
     */
    public function scheduledMessages(): Collection
    {
        return $this
            ->messages()
            ->where('delivered', false)
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '>', Carbon::now())
            ->with('users:id,uuid,name,username,avatar_path')
            ->get();
    }

    /**
     * Return a limited activities relation (shallow wrapper).
     *
     * @return HasMany<Activity>
     */
    public function limitedActivities(): HasMany
    {
        return $this->activities()->take(5);
    }

    public function state(): string
    {
        return $this->deleted_at ? 'trashed' : 'active';
    }

    public function getStateAttribute(): string
    {
        return $this->deleted_at ? 'trashed' : 'active';
    }

    /**
     * Get meetings releated to the project.
     *
     * @return HasMany<Meeting>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Scope projects created in a given year/month.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeCreatedIn(Builder $query, ?int $year = null, ?int $month = null): Builder
    {
        if ($year) {
            $query->whereYear('projects.created_at', $year);
            if ($month) {
                $query->whereMonth('projects.created_at', $month);
            }
        }

        return $query;
    }

    #[Override]
    protected static function boot(): void
    {
        parent::boot();

        static::forceDeleted(function ($project): void {
            $project->activities()->delete();
        });
    }
}
