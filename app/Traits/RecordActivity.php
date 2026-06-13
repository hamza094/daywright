<?php

declare(strict_types=1);

namespace App\Traits;

use App\Events\ActivityLogged;
use App\Events\DashboardActivity;
use App\Models\Activity;
use App\Models\Project;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

trait RecordActivity
{
    /**
     * The project's old attributes.
     *
     * @var array<string, mixed>
     */
    private $oldAttributes = [];

    /**
     * Boot the trait to record activity.
     */
    public static function bootRecordActivity(): void
    {
        foreach (static::recordableEvents() as $event) {
            static::$event(function ($model) use ($event): void {
                // Only record activity on soft delete, not force delete, for Project model
                if (
                    $event === 'deleted' &&
                    class_basename($model) === 'Project' &&
                    method_exists($model, 'isForceDeleting') &&
                    $model->isForceDeleting()
                ) {
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
     * Create the activity log entry in the database.
     */
    public function recordActivity(string $description, array $affectedUserIds = []): void
    {
        $activity = $this->createActivityLog($description, $affectedUserIds);

        if ($this->shouldBroadcast($description)) {
            $this->rateLimitedBroadcast($activity);
        }

        // DashboardActivity::dispatch($activity);
    }

    /**
     * The activity feed for the project.
     *
     * @return HasMany|MorphMany<Activity>
     */
    public function activities(): HasMany|MorphMany
    {
        $query = ($this instanceof Project)
        ? $this->hasMany(Activity::class)
        : $this->morphMany(Activity::class, 'subject');

        return $query
            ->with([
                'user:id,uuid,name,username,avatar_path',
                'subject' => fn ($query) => $query->withTrashed(),
            ])
            ->latest()
            ->orderByDesc('id'); // Break ties when multiple activities share same created_at
    }

    /**
     * Fetch the model events that should trigger activity.
     *
     * @return array<int, string>
     */
    protected static function recordableEvents(): array
    {
        return static::$recordableEvents ?? ['created', 'updated', 'deleted'];
    }

    /**
     * Get the description of the activity.
     */
    protected function activityDescription(string $event): string
    {
        return "{$event}_".Str::lower(class_basename($this));
    }

    /**
     * Create the activity log entry in the database.
     */
    protected function createActivityLog(string $description, array $affectedUserIds): Activity
    {
        $projectId = $this->resolveProjectId();

        $activity = $this->activities()->create([
            'user_id' => $this->resolveUserId(),
            'description' => $description,
            'changes' => $this->activityChanges(),
            'project_id' => $projectId,
            'affected_users' => $affectedUserIds,
        ]);

        return $activity->loadMissing(['subject', 'user']);
    }

    /**
     * Determine if the activity should be broadcast.
     */
    protected function shouldBroadcast(string $description): bool
    {
        $skipKeywords = ['_stage', '_taskstatus', '_message', '_sent', '_accepted', '_removed'];

        return ! Str::contains($description, $skipKeywords);
    }

    /**
     * Perform broadcast under rate limiting.
     */
    protected function rateLimitedBroadcast(Activity $activity): void
    {
        RateLimiter::attempt(
            'broadcast-activity:'.auth()->id(),
            5,
            fn () => ActivityLogged::dispatch($activity, $activity->project_id)
        );
    }

    /**
     * Resolve the project ID depending on the model.
     */
    protected function resolveProjectId(): ?int
    {
        return class_basename($this) === 'Project' ? $this->id : $this->project_id;
    }

    /**
     * Fetch the changes to the model.
     */
    protected function activityChanges(): ?array
    {
        if (! $this->wasChanged()) {
            return null;
        }

        $oldAttributes = $this->convertEnumsToValues($this->oldAttributes);
        $currentAttributes = $this->convertEnumsToValues($this->getAttributes());

        return [
            'before' => Arr::except(
                array_diff($oldAttributes, $currentAttributes), 'updated_at'
            ),
            'after' => Arr::except(
                $this->getChanges(), 'updated_at'
            ),
        ];
    }

    /**
     * Convert enum values to their backed values for array comparison.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function convertEnumsToValues(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if ($value instanceof BackedEnum) {
                $attributes[$key] = $value->value;
            } elseif ($value instanceof \Illuminate\Database\Query\Expression) {
                // Skip Expression objects from comparison as they represent SQL expressions
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /**
     * Resolve the user ID for the activity.
     */
    private function resolveUserId(): int
    {
        $authId = auth()->id();

        if ($authId !== null) {
            return $authId;
        }

        $relation = ($this->project ?? $this);

        // Try to load the user relationship if not already loaded
        if (! $relation->relationLoaded('user')) {
            $relation->load('user');
        }

        $user = $relation->user;

        if ($user === null) {
            throw new RuntimeException('Unable to resolve user ID for activity');
        }

        return $user->id;
    }
}
