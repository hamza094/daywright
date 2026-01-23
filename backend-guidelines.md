# DayWright Backend Development Guidelines

> **Purpose**: This document serves as the definitive reference for Laravel backend development patterns, conventions, and best practices established in the DayWright project.

---

## Table of Contents

1. [General Standards](#1-general-standards)
2. [Actions](#2-actions)
3. [Services](#3-services)
4. [Repositories](#4-repositories)
5. [Data Transfer Objects (DTOs)](#5-data-transfer-objects-dtos)
6. [Controllers](#6-controllers)
7. [Form Requests](#7-form-requests)
8. [API Resources](#8-api-resources)
9. [Models](#9-models)
10. [Enums](#10-enums)
11. [Events & Listeners](#11-events--listeners)
12. [Jobs](#12-jobs)
13. [Policies](#13-policies)
14. [Traits](#14-traits)
15. [Query Builders](#15-query-builders)
16. [Validation Rules](#16-validation-rules)
17. [Notifications](#17-notifications)
18. [Interfaces](#18-interfaces)
19. [Testing](#19-testing)
20. [API Response Standards](#20-api-response-standards)

---

## 1. General Standards

### Strict Typing

Every PHP file MUST start with strict type declaration:

```php
<?php

declare(strict_types=1);

namespace App\...;
```

### PHP Version Requirements

- **Minimum**: PHP 8.2.17
- Use constructor property promotion
- Use named arguments where clarity improves
- Use `readonly` properties for immutability
- Use `final` classes where inheritance is not intended

### Dependency Injection

- Prefer constructor injection with `private readonly` modifier
- Use method injection for controller actions and job handlers
- Never use `app()` helper or service locator pattern

```php
// ✅ Good
public function __construct(
    private readonly ProjectRepository $projectRepository,
    private readonly TaskHealthMetricAction $taskHealthAction,
) {}

// ❌ Bad
public function __construct() {
    $this->projectRepository = app(ProjectRepository::class);
}
```

### Namespace Organization

```
App\
├── Actions\{Domain}\           # Single-purpose action classes
├── DataTransferObjects\{Domain}\ # DTOs grouped by domain
├── Enums\                      # All enums at root level
├── Events\                     # Domain events
├── Exceptions\{Integration}\   # Custom exceptions by integration
├── Http\
│   ├── Controllers\Api\V1\     # Versioned API controllers
│   ├── Requests\Api\V1\        # Versioned form requests
│   └── Resources\Api\V1\       # Versioned API resources
├── Jobs\Webhooks\              # Webhook-specific jobs
├── Models\                     # Eloquent models
├── Notifications\{Domain}\     # Grouped notifications
├── QueryBuilder\               # Custom query builders
├── Repository\                 # Repository classes
├── Rules\                      # Custom validation rules
├── Services\Api\V1\            # Versioned services
│   ├── Dashboard\              # Feature-specific services
│   └── Task\
└── Traits\                     # Reusable traits
```

---

## 2. Actions

### Purpose

Actions are single-responsibility classes that encapsulate specific business logic operations.

### Location

`app/Actions/` or `app/Actions/{Domain}/`

### Naming Convention

**Format**: `{Verb}{Subject}Action.php` or `{Subject}{Verb}Action.php`

**Examples**:

- `DeleteProfileAction`
- `TaskDueAction`
- `ProjectHealthMetricAction`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Task;

class TaskDueAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function shouldNotify(Task $task): bool
    {
        return $task->due_at !== null
            && $task->due_at->isFuture()
            && $task->status_id !== TaskStatus::COMPLETED;
    }

    public function sendNotification(Task $task): void
    {
        if (! $this->shouldNotify($task)) {
            return;
        }

        $this->notificationService->send($task);
    }
}
```

### Guidelines

- ✅ Use explicit return types (`bool`, `void`, `float`, `array`)
- ✅ Inject dependencies via constructor
- ✅ Keep actions focused on a single responsibility
- ✅ Use other actions as dependencies for composition
- ❌ Do not extend base classes
- ❌ Do not handle HTTP concerns (that's for controllers)

### Composite Action Example

```php
<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

class ProjectHealthMetricAction
{
    public function __construct(
        private readonly TaskHealthMetricAction $taskHealthAction,
        private readonly TeamCollaborationMetricAction $collaborationHealthAction,
        private readonly TimelineAdherenceMetricAction $timelineHealthAction,
        private readonly ActivityMetricAction $activityHealthAction,
    ) {}

    public function execute(Project $project): float
    {
        $taskHealth = $this->taskHealthAction->execute($project);
        $collaborationHealth = $this->collaborationHealthAction->execute($project);
        $timelineHealth = $this->timelineHealthAction->execute($project);
        $activityHealth = $this->activityHealthAction->execute($project);

        return round(
            ($taskHealth * 0.35) +
            ($collaborationHealth * 0.25) +
            ($timelineHealth * 0.25) +
            ($activityHealth * 0.15),
            1
        );
    }
}
```

---

## 3. Services

### Purpose

Services orchestrate business logic, coordinating between repositories, actions, and external integrations.

### Location

`app/Services/Api/V1/` or `app/Services/Api/V1/{Feature}/`

### Naming Convention

**Format**: `{Domain}Service.php`

**Examples**:

- `ProjectService`
- `UserService`
- `DashboardInsightsService`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Support\Facades\DB;

final readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function updateUser(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $user->update($data);

            if (isset($data['info'])) {
                $user->info()->updateOrCreate([], $data['info']);
            }
        });
    }

    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
```

### Guidelines

- ✅ Use `final readonly` for immutable services
- ✅ Wrap multi-step operations in `DB::transaction()`
- ✅ Use PHPDoc for array parameter types: `@param array<string, mixed>`
- ✅ Fire events after successful operations
- ✅ Dispatch notifications from services
- ❌ Do not access `Request` directly (pass data as parameters)
- ❌ Do not return HTTP responses

---

## 4. Repositories

### Purpose

Repositories encapsulate data access logic, providing a clean API for querying the database.

### Location

`app/Repository/`

### Naming Convention

**Format**: `{Domain}Repository.php`

**Examples**:

- `ProjectRepository`
- `TaskRepository`
- `DashboardInsightsRepository`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardInsightsRepository
{
    public function getUserProjects(int $userId): Collection
    {
        return Project::where('user_id', $userId)
            ->with(['tasks', 'members'])
            ->get();
    }

    public function getTaskCompletionRate(int $userId): float
    {
        $total = $this->baseUserTasks($userId)->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->baseUserTasks($userId)
            ->where('status_id', TaskStatus::COMPLETED)
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    public function getOverdueTasksCount(int $userId): int
    {
        return $this->baseUserTasks($userId)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status_id', '!=', TaskStatus::COMPLETED)
            ->count();
    }

    private function baseUserTasks(int $userId): Builder
    {
        return Task::whereHas('project', fn ($q) => $q->where('user_id', $userId));
    }
}
```

### Guidelines

- ✅ Return typed collections (`Collection`, `Builder`, model types)
- ✅ Use `private` helper methods for reusable query logic
- ✅ Optimize queries with eager loading to avoid N+1
- ✅ Method prefixes: `get`, `find`, `filter`, `search`, `count`
- ❌ Do not include business logic (that belongs in Services/Actions)
- ❌ Do not fire events from repositories

---

## 5. Data Transfer Objects (DTOs)

### Purpose

DTOs are immutable value objects for transferring data between layers with type safety.

### Location

`app/DataTransferObjects/{Domain}/`

### Naming Convention

**Format**: `{Purpose}{Type}.php`

**Examples**:

- `AuthPayload`
- `LoginResult`
- `AccessTokenDetails`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Models\User;

final class AuthPayload
{
    public function __construct(
        public User $user,
        public ?string $accessToken = null,
        public string $message = 'User authenticated successfully',
        public string $status = 'success',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'access_token' => $this->accessToken,
            'message' => $this->message,
            'status' => $this->status,
        ];
    }
}
```

### Guidelines

- ✅ Use `final` class modifier
- ✅ Use constructor property promotion with `public` properties
- ✅ Provide default values for optional fields
- ✅ Include `toArray()` method for serialization
- ✅ Use nullable types with `?` syntax
- ❌ Do not add behavior/methods beyond data transformation

---

## 6. Controllers

### Purpose

Controllers handle HTTP requests, delegate to services, and return standardized responses.

### Location

`app/Http/Controllers/Api/V1/`

### Naming Convention

**Format**: `{Resource}Controller.php`

**Examples**:

- `ProjectController`
- `TaskController`
- `UserController`

### Hierarchy

```
Controller (base)
└── ApiController
    └── V1 Controllers
```

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ProjectStoreRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Services\Api\V1\ProjectService;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProjectController extends ApiController
{
    use ApiResponseHelpers;

    /**
     * Create a new project.
     *
     * @operationId createProject
     * @tags Projects
     */
    public function store(ProjectStoreRequest $request, ProjectService $service): JsonResponse
    {
        $project = $service->createProject(
            Auth::user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Project Created Successfully',
            'project' => new ProjectResource($project),
        ], 201);
    }

    /**
     * Update a project.
     *
     * @operationId updateProject
     * @tags Projects
     */
    public function update(
        ProjectUpdateRequest $request,
        Project $project,
        ProjectService $service
    ): JsonResponse {
        $this->authorize('manage', $project);

        $service->updateProject($project, $request->validated());

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => new ProjectResource($project->fresh()),
        ]);
    }

    /**
     * Delete a project.
     *
     * @operationId deleteProject
     * @tags Projects
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('manage', $project);

        $project->delete();

        return $this->respondNoContent();
    }
}
```

### Guidelines

- ✅ Extend `ApiController` for API endpoints
- ✅ Use `ApiResponseHelpers` trait for standardized responses
- ✅ Use method injection for Request and Service dependencies
- ✅ Use `$this->authorize()` for policy checks
- ✅ Use `$request->validated()` or `$request->safe()` for clean data
- ✅ Add docblocks with `@operationId` and `@tags` for API documentation
- ✅ Return JSON with `message` + `resource` pattern
- ❌ Do not put business logic in controllers
- ❌ Do not access database directly (use services/repositories)

### Response Status Codes

| Action | Status Code | Method                         |
| ------ | ----------- | ------------------------------ |
| Create | 201         | `response()->json([...], 201)` |
| Read   | 200         | `response()->json([...])`      |
| Update | 200         | `response()->json([...])`      |
| Delete | 204         | `$this->respondNoContent()`    |
| Error  | 4xx/5xx     | `$this->respondError(...)`     |

---

## 7. Form Requests

### Purpose

Form Requests handle validation and authorization for incoming HTTP requests.

### Location

`app/Http/Requests/Api/V1/`

### Naming Convention

**Format**: `{Resource}{Action}Request.php`

**Examples**:

- `ProjectStoreRequest`
- `ProjectUpdateRequest`
- `TaskRequest`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled at controller/route level
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * @example The Dimension
             */
            'name' => [
                'required',
                'string',
                'max:150',
                'min:4',
                Rule::unique('projects')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],

            /**
             * @example This is a description of the project
             */
            'about' => 'required|min:15',

            /**
             * @example 1
             */
            'stage_id' => 'required|int|between:1,5',

            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required_with:tasks|string|min:3',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'name.unique' => 'You already have a project with this name.',
            'about.min' => 'Description must be at least 15 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->name),
        ]);
    }
}
```

### Guidelines

- ✅ Include `@example` annotations for API documentation (Scramble/OpenAPI)
- ✅ Use `Rule::unique()` with closures for complex uniqueness checks
- ✅ Override `messages()` for user-friendly error messages
- ✅ Use `prepareForValidation()` for pre-validation data manipulation
- ✅ Access route parameters with `$this->route('param')`
- ✅ Use array notation for complex rules (easier to read)

---

## 8. API Resources

### Purpose

Resources transform Eloquent models into standardized JSON API responses.

### Location

`app/Http/Resources/Api/V1/`

### Naming Convention

**Format**: `{Model}Resource.php` (singular) or `{Model}sResource.php` (collection/list view)

**Examples**:

- `ProjectResource` (full/single)
- `ProjectsResource` (list/collection)
- `TaskResource`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $isShowRoute = $request->routeIs('projects.show');

        return [
            /** @example 1 */
            'id' => $this->id,

            /** @example the-dimension */
            'slug' => $this->slug,

            /** @example My Project Name */
            'name' => $this->name,

            /** @example 85.5 */
            'health_score' => $this->health_score,

            /** @example warm */
            'health_status' => $this->health_status,

            // Conditional fields
            'about' => $this->when($isShowRoute, $this->about),

            // Date formatting
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),

            // Soft delete handling
            'deleted_at' => $this->when(
                $this->deleted_at !== null,
                fn () => $this->deleted_at->diffForHumans()
            ),

            // Relationships (only when loaded)
            'stage' => new StageResource($this->whenLoaded('stage')),
            'tasks' => TasksResource::collection($this->whenLoaded('tasks')),
            'owner' => new UserResource($this->whenLoaded('user')),

            // Counts
            'tasks_count' => $this->when(
                $this->tasks_count !== null,
                $this->tasks_count
            ),
        ];
    }
}
```

### Guidelines

- ✅ Use `@mixin` PHPDoc for IDE autocompletion
- ✅ Use `@example` for API documentation
- ✅ Use `$this->when()` for conditional fields
- ✅ Use `$this->whenLoaded()` for relationships (prevents N+1)
- ✅ Check route context with `$request->routeIs()`
- ✅ Format dates consistently (ISO + human-readable)
- ❌ Do not include sensitive data (passwords, tokens)
- ❌ Do not call database queries inside resources

---

## 9. Models

### Purpose

Eloquent models represent database tables with relationships, scopes, and domain logic.

### Location

`app/Models/`

### Naming Convention

**Format**: `{Entity}.php` (singular, PascalCase)

**Examples**:

- `Project`
- `Task`
- `User`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectHealthStatus;
use App\QueryBuilder\ProjectQueryBuilder;
use App\Traits\RecordActivity;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use RecordActivity;
    use Sluggable;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<int, string>
     */
    protected $appends = ['health_status'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'stage_updated_at' => 'datetime',
        'health_score' => 'float',
    ];

    /**
     * @var array<int, string>
     */
    protected static $recordableEvents = ['created', 'updated', 'deleted', 'restored'];

    // =========================================================================
    // QUERY BUILDER
    // =========================================================================

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    public function newEloquentBuilder($query): ProjectQueryBuilder
    {
        return new ProjectQueryBuilder($query);
    }

    // =========================================================================
    // ROUTE MODEL BINDING
    // =========================================================================

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // =========================================================================
    // SLUGGABLE
    // =========================================================================

    public function sluggable(): array
    {
        return [
            'slug' => ['source' => 'name'],
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return BelongsTo<User, Project>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->latest();
    }

    /**
     * @return BelongsToMany<User>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['status', 'role'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getHealthStatusAttribute(): string
    {
        return match (true) {
            $this->health_score >= 70 => ProjectHealthStatus::HOT->value,
            $this->health_score >= 40 => ProjectHealthStatus::WARM->value,
            default => ProjectHealthStatus::COLD->value,
        };
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrderByHealthScore(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('health_score', $direction);
    }

    public function scopeCreatedIn(Builder $query, ?int $year, ?int $month): Builder
    {
        return $query
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month));
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // =========================================================================
    // BUSINESS METHODS
    // =========================================================================

    public function addTask(string $title): Task
    {
        return $this->tasks()->create([
            'title' => $title,
            'status_id' => TaskStatus::PENDING,
        ]);
    }

    public function path(): string
    {
        return "/projects/{$this->slug}";
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
```

### Model Structure Order

1. Traits (use statements)
2. Properties (`$guarded`, `$casts`, `$appends`, etc.)
3. Static properties
4. Boot methods
5. Query Builder override
6. Route model binding
7. Package configurations (sluggable, etc.)
8. Relationships
9. Accessors/Mutators
10. Scopes
11. Business methods

### Guidelines

- ✅ Use `$guarded = []` (rely on Form Request validation)
- ✅ Define `$casts` for type casting
- ✅ Use `$appends` for computed attributes
- ✅ Override `getRouteKeyName()` for slug-based routing
- ✅ Use custom Query Builders via `newEloquentBuilder()`
- ✅ PHPDoc relationship return types: `@return BelongsTo<User, Project>`
- ✅ Organize with section comments
- ❌ Do not use `$fillable` (use `$guarded = []` instead)

---

## 10. Enums

### Purpose

Enums define fixed sets of values with associated logic.

### Location

`app/Enums/`

### Naming Convention

**Format**: `{Domain}{Concept}.php`

**Examples**:

- `TaskStatus`
- `ProjectHealthStatus`
- `OAuthProvider`

### Structure Template (Backed Enum - Preferred)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectHealthStatus: string
{
    case HOT = 'hot';
    case WARM = 'warm';
    case COLD = 'cold';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::HOT => 'Healthy',
            self::WARM => 'Needs Attention',
            self::COLD => 'At Risk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HOT => 'green',
            self::WARM => 'yellow',
            self::COLD => 'red',
        };
    }
}
```

### Structure Template (Integer Enum)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: int
{
    case PENDING = 1;
    case IN_PROGRESS = 2;
    case COMPLETED = 4;

    /**
     * @return array<int>
     */
    public static function active(): array
    {
        return [
            self::PENDING->value,
            self::IN_PROGRESS->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
        };
    }
}
```

### Guidelines

- ✅ Use PHP 8.1+ backed enums
- ✅ Add static helper methods: `values()`, `active()`, `all()`
- ✅ Add instance methods: `label()`, `color()`, `icon()`
- ✅ Use `match` expressions for value mapping

---

## 11. Events & Listeners

### Purpose

Events represent domain occurrences; Listeners handle side effects.

### Location

- Events: `app/Events/`
- Listeners: `app/Listeners/`

### Naming Convention

- **Events**: `{Subject}{Action}.php` - `ProjectHealthUpdated`, `NewMessage`
- **Listeners**: `{Action}{Subject}.php` - `SendPasswordUpdateEmail`, `SaveUserTimezone`

### Event Template

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectHealthUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $broadcastQueue = 'metrics';

    public function __construct(
        public Project $project,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("project.{$this->project->id}.health");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->project->id,
            'health_score' => $this->project->health_score,
            'health_status' => $this->project->health_status,
        ];
    }
}
```

### Listener Template

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordUpdateEvent;
use App\Mail\PasswordUpdate;
use Illuminate\Support\Facades\Mail;

class SendPasswordUpdateEmail
{
    public function __construct() {}

    public function handle(PasswordUpdateEvent $event): void
    {
        Mail::to($event->user)->send(
            new PasswordUpdate($event->updatedAt)
        );
    }
}
```

### Guidelines

- ✅ Implement `ShouldBroadcast` for real-time events
- ✅ Define `broadcastQueue` for queue routing
- ✅ Use `PrivateChannel` for authenticated users
- ✅ Type-hint specific event in listener's `handle()` method
- ✅ Keep listeners focused on single actions

---

## 12. Jobs

### Purpose

Jobs encapsulate work to be queued and processed asynchronously.

### Location

`app/Jobs/` or `app/Jobs/{Domain}/`

### Naming Convention

**Format**: `{Verb}{Subject}Job.php` or `{Action}{Subject}.php`

**Examples**:

- `QueuedVerifyEmailJob`
- `RecalculateProjectHealth`
- `ProcessZoomWebhook`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Events\ProjectHealthUpdated;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateProjectHealth implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $projectId,
        public ?float $precomputedScore = null,
        public bool $broadcast = false,
    ) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [40, 60];
    }

    public function handle(ProjectHealthMetricAction $action): void
    {
        $project = Project::findOrFail($this->projectId);

        $score = $this->precomputedScore ?? $action->execute($project);

        $project->update(['health_score' => $score]);

        if ($this->broadcast) {
            event(new ProjectHealthUpdated($project->fresh()));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecalculateProjectHealth failed', [
            'project_id' => $this->projectId,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
```

### Guidelines

- ✅ Implement `ShouldQueue`
- ✅ Use standard traits: `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`
- ✅ Define `$tries`, `$timeout`, and `backoff()`
- ✅ Use method injection in `handle()` for dependencies
- ✅ Implement `failed()` for failure handling
- ✅ Use primitive IDs instead of models (avoids serialization issues)
- ✅ Log failures with context

---

## 13. Policies

### Purpose

Policies define authorization logic for model access.

### Location

`app/Policies/`

### Naming Convention

**Format**: `{Model}sPolicy.php` (plural)

**Examples**:

- `ProjectsPolicy`
- `TasksPolicy`
- `UsersPolicy`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProjectsPolicy
{
    use HandlesAuthorization;

    /**
     * Admin bypass for all actions.
     */
    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null; // Fall through to specific checks
    }

    /**
     * Can the user manage (update/delete) this project?
     */
    public function manage(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }

    /**
     * Can the user access (view) this project?
     */
    public function access(User $user, Project $project): Response
    {
        $isOwner = $user->is($project->user);
        $isMember = $project->activeMembers->contains('id', $user->id);

        return $isOwner || $isMember
            ? Response::allow()
            : Response::deny("Only project owner and members can access this project.");
    }

    /**
     * Can the user create projects?
     */
    public function create(User $user): bool
    {
        return $user->hasActiveSubscription();
    }
}
```

### Guidelines

- ✅ Use `HandlesAuthorization` trait
- ✅ Implement `before()` for admin bypass
- ✅ Return `bool` for simple checks
- ✅ Return `Response` for checks with detailed messages
- ✅ Common methods: `access`, `manage`, `view`, `create`, `update`, `delete`

---

## 14. Traits

### Purpose

Traits provide reusable functionality across multiple classes.

### Location

`app/Traits/`

### Naming Convention

**Format**: `{Capability}.php`

**Examples**:

- `RecordActivity`
- `HasSubscription`
- `ProjectSetup` (for tests)

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Activity;

trait RecordActivity
{
    /**
     * @var array<string, mixed>
     */
    private array $oldAttributes = [];

    public static function bootRecordActivity(): void
    {
        foreach (static::recordableEvents() as $event) {
            static::$event(function ($model) use ($event): void {
                $model->recordActivity($model->activityDescription($event), []);
            });
        }

        static::updating(function ($model): void {
            $model->oldAttributes = $model->getOriginal();
        });
    }

    /**
     * @param array<int> $affectedUserIds
     */
    public function recordActivity(string $description, array $affectedUserIds = []): void
    {
        $this->activities()->create([
            'user_id' => auth()->id(),
            'description' => $description,
            'changes' => $this->activityChanges(),
            'affected_user_ids' => $affectedUserIds,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function recordableEvents(): array
    {
        return static::$recordableEvents ?? ['created', 'updated', 'deleted'];
    }

    protected function activityDescription(string $event): string
    {
        return "{$event}_" . strtolower(class_basename($this));
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>|null
     */
    protected function activityChanges(): ?array
    {
        // Return changes for audit logging
    }
}
```

### Guidelines

- ✅ Use `boot{TraitName}()` for model event registration
- ✅ Define protected/private helper methods
- ✅ Allow customization via static properties
- ✅ Document with PHPDoc return types
- ✅ Keep traits focused and cohesive

---

## 15. Query Builders

### Purpose

Custom Query Builders extend Eloquent's Builder with model-specific query methods.

### Location

`app/QueryBuilder/`

### Naming Convention

**Format**: `{Model}QueryBuilder.php`

**Examples**:

- `ProjectQueryBuilder`
- `TaskQueryBuilder`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\App\Models\Task>
 *
 * @method $this onlyTrashed()
 * @method $this withTrashed()
 */
class TaskQueryBuilder extends Builder
{
    public function completed(): self
    {
        return $this->where('status_id', TaskStatus::COMPLETED->value);
    }

    public function pending(): self
    {
        return $this->where('status_id', TaskStatus::PENDING->value);
    }

    public function inProgress(): self
    {
        return $this->where('status_id', TaskStatus::IN_PROGRESS->value);
    }

    public function active(): self
    {
        return $this->whereIn('status_id', TaskStatus::active());
    }

    public function overdue(): self
    {
        return $this->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status_id', '!=', TaskStatus::COMPLETED->value);
    }

    public function dueWithin(int $days): self
    {
        return $this->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDays($days)]);
    }

    public function forProject(int $projectId): self
    {
        return $this->where('project_id', $projectId);
    }

    public function assignedTo(int $userId): self
    {
        return $this->whereHas('assignees', fn ($q) => $q->where('users.id', $userId));
    }
}
```

### Guidelines

- ✅ Extend `Illuminate\Database\Eloquent\Builder`
- ✅ Return `self` for method chaining
- ✅ Use `@extends` and `@method` PHPDoc for IDE support
- ✅ Group related query conditions logically
- ✅ Connect to model via `newEloquentBuilder()` override

---

## 16. Validation Rules

### Purpose

Custom validation rules encapsulate complex validation logic.

### Location

`app/Rules/`

### Naming Convention

**Format**: `{ValidationName}.php`

**Examples**:

- `ActiveProjectMember`
- `TaskAssigneeMember`
- `MeetingDateTime`

### Structure Template (Invokable Rule - Laravel 9+)

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Task;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveProjectMember implements ValidationRule
{
    public function __construct(
        protected Task $task,
    ) {}

    /**
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $activeProjectMemberIds = $this->task
            ->project
            ->activeMembers()
            ->pluck('users.id')
            ->toArray();

        $invalidMembers = array_diff($value, $activeProjectMemberIds);

        if ($invalidMembers !== []) {
            $fail('One or more users are not active project members.');
        }
    }
}
```

### Guidelines

- ✅ Implement `ValidationRule` (Laravel 9+)
- ✅ Accept dependencies via constructor
- ✅ Use `$fail` closure for error messages
- ✅ Provide meaningful, user-friendly error messages

---

## 17. Notifications

### Purpose

Notifications handle multi-channel user notifications.

### Location

`app/Notifications/` or `app/Notifications/{Domain}/`

### Naming Convention

**Format**: `{Subject}{Action}.php`

**Examples**:

- `TaskDue`
- `ProjectUpdated`
- `UserMentioned`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDue extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Carbon $dueDate,
        protected string $taskTitle,
        protected string $projectName,
        protected string $taskUrl,
    ) {}

    /**
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task Due: {$this->taskTitle}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your task \"{$this->taskTitle}\" in project \"{$this->projectName}\" is due {$this->dueDate->diffForHumans()}.")
            ->action('View Task', $this->taskUrl)
            ->line('Please complete it before the deadline.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'task_title' => $this->taskTitle,
            'project_name' => $this->projectName,
            'due_date' => $this->dueDate->toISOString(),
            'task_url' => $this->taskUrl,
            'message' => $this->buildMessage(),
        ];
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    private function buildMessage(): string
    {
        return "Task \"{$this->taskTitle}\" is due {$this->dueDate->diffForHumans()}.";
    }
}
```

### Guidelines

- ✅ Implement `ShouldBroadcast` and `ShouldQueue` for async delivery
- ✅ Use `Queueable` trait
- ✅ Define channels in `via()` method
- ✅ Implement `toMail()`, `toArray()`, `toBroadcast()` for each channel
- ✅ Use private helper methods for message formatting

---

## 18. Interfaces

### Purpose

Interfaces define contracts for services and integrations.

### Location

`app/Interfaces/`

### Naming Convention

**Format**: `{Service}.php`

**Examples**:

- `Zoom`
- `Paddle`
- `PaymentGateway`

### Structure Template

```php
<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\Models\Meeting;
use App\Models\User;

interface Zoom
{
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails;

    public function authorize(AuthorizationCallbackDetails $callbackDetails): AccessTokenDetails;

    /**
     * @param array<string, mixed> $validated
     */
    public function createMeeting(array $validated, User $user): Meeting;

    public function updateMeeting(int $meetingId, array $data, User $user): Meeting;

    public function deleteMeeting(int $meetingId, User $user): bool;

    public function getMeeting(int $meetingId, User $user): Meeting;
}
```

### Guidelines

- ✅ Use DTOs for parameter and return types
- ✅ Define clear method contracts
- ✅ Use PHPDoc for array parameter types
- ✅ Bind interfaces to implementations in `AppServiceProvider`

---

## 19. Testing

### Directory Structure

```
tests/
├── TestCase.php              # Base test case
├── CreatesApplication.php    # Application bootstrapping
├── Feature/
│   └── Api/
│       ├── V1/               # API version tests
│       │   ├── ProjectFeatureTest.php
│       │   └── TaskTest.php
│       ├── Auth/             # Authentication tests
│       ├── Controllers/      # Controller-specific tests
│       ├── Services/         # Service tests
│       └── Middleware/       # Middleware tests
├── Unit/
│   ├── ProjectTest.php
│   ├── Repository/           # Repository unit tests
│   └── Services/             # Service unit tests
├── Fixtures/                 # Test fixtures (JSON, etc.)
├── Support/                  # Test helpers and builders
└── Traits/                   # Reusable test traits
```

### Test File Naming

- **Feature tests**: `{Feature}Test.php` or `{Feature}FeatureTest.php`
- **Unit tests**: `{Subject}Test.php`

### Base Test Case

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }
}
```

### Feature Test Template

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFeatureTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    private const PROJECTS_ROUTE = 'projects.store';
    private const PROJECTS_INDEX = 'projects.index';

    /** @test */
    public function authenticated_user_can_create_project(): void
    {
        // Arrange
        $projectData = [
            'name' => 'My Project Name',
            'about' => 'This is a description of the project',
            'stage_id' => 1,
        ];

        // Act
        $response = $this->postJson(route(self::PROJECTS_ROUTE), $projectData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'project' => ['id', 'slug', 'name'],
            ]);

        $this->assertDatabaseHas('projects', ['name' => 'My Project Name']);
    }

    /** @test */
    public function project_requires_a_name(): void
    {
        $response = $this->postJson(route(self::PROJECTS_ROUTE), [
            'name' => '',
            'about' => 'This is a description',
            'stage_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** @test */
    public function user_can_only_see_their_own_projects(): void
    {
        // Arrange
        $otherUser = User::factory()->create();
        Project::factory()->for($otherUser)->create();

        // Act
        $response = $this->getJson(route(self::PROJECTS_INDEX));

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data'); // Only user's project
    }
}
```

### Unit Test Template

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Actions\ProjectMetrics\TaskHealthMetricAction;
use App\Models\Project;
use Mockery;
use Tests\TestCase;

class ProjectInsightsCalculationsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function calculates_project_health_from_all_metrics(): void
    {
        // Arrange
        $project = Project::factory()->make();

        $taskHealth = Mockery::mock(TaskHealthMetricAction::class);
        $taskHealth->shouldReceive('execute')->once()->andReturn(80.0);

        $collaborationHealth = Mockery::mock(TeamCollaborationMetricAction::class);
        $collaborationHealth->shouldReceive('execute')->once()->andReturn(70.0);

        $action = new ProjectHealthMetricAction(
            $taskHealth,
            $collaborationHealth,
            // ... other mocks
        );

        // Act
        $result = $action->execute($project);

        // Assert
        $this->assertEquals(68.3, $result);
    }
}
```

### Setup Trait Template

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Http\Middleware\CheckSubscription;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait ProjectSetup
{
    public Project $project;
    public User $user;
    public TaskStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'testuser@example.org',
        ]);

        Sanctum::actingAs($this->user);

        $this->status = TaskStatus::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();

        $this->withoutMiddleware([CheckSubscription::class]);
    }
}
```

### Testing Guidelines

- ✅ Use `/** @test */` annotation or `test_` prefix
- ✅ Method naming: `snake_case` describing behavior
- ✅ Follow AAA pattern: Arrange, Act, Assert
- ✅ Use `RefreshDatabase` trait for database tests
- ✅ Mock external dependencies with Mockery
- ✅ Use Factory patterns for test data
- ✅ Use `assertJsonValidationErrors()` for validation tests
- ✅ Define route constants for repeated route names
- ✅ Create setup traits for common test configuration
- ✅ Use `Http::preventStrayRequests()` to catch unmocked HTTP calls

---

## 20. API Response Standards

### Success Response Structure

```php
// Create (201)
return response()->json([
    'message' => 'Resource created successfully',
    'resource_name' => new ResourceClass($model),
], 201);

// Read/Update (200)
return response()->json([
    'message' => 'Operation completed successfully',
    'resource_name' => new ResourceClass($model),
]);

// List with pagination
return response()->json([
    'message' => 'Resources retrieved successfully',
    'data' => ResourceClass::collection($paginator->items()),
    'meta' => [
        'current_page' => $paginator->currentPage(),
        'last_page' => $paginator->lastPage(),
        'per_page' => $paginator->perPage(),
        'total' => $paginator->total(),
    ],
]);

// Delete (204)
return $this->respondNoContent();
```

### Error Response Structure

```php
// Validation Error (422)
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Error message here."]
    }
}

// Not Found (404)
{
    "message": "Resource not found."
}

// Unauthorized (401)
{
    "message": "Unauthenticated."
}

// Forbidden (403)
{
    "message": "This action is unauthorized."
}

// Server Error (500)
{
    "message": "An unexpected error occurred."
}
```

### Using ApiResponseHelpers Trait

```php
use F9Web\ApiResponseHelpers;

class MyController extends ApiController
{
    use ApiResponseHelpers;

    // Available methods:
    // $this->respondWithSuccess($data)
    // $this->respondOk($message)
    // $this->respondUnAuthenticated($message)
    // $this->respondForbidden($message)
    // $this->respondError($message)
    // $this->respondNotFound($message)
    // $this->respondNoContent()
    // $this->setDefaultSuccessResponse($data)
}
```

---

## Quick Reference Card

### File Naming Patterns

| Type          | Pattern                         | Example                        |
| ------------- | ------------------------------- | ------------------------------ |
| Action        | `{Verb}{Subject}Action.php`     | `DeleteProfileAction.php`      |
| Service       | `{Domain}Service.php`           | `ProjectService.php`           |
| Repository    | `{Domain}Repository.php`        | `TaskRepository.php`           |
| DTO           | `{Purpose}{Type}.php`           | `AuthPayload.php`              |
| Controller    | `{Resource}Controller.php`      | `ProjectController.php`        |
| Request       | `{Resource}{Action}Request.php` | `ProjectStoreRequest.php`      |
| Resource      | `{Model}Resource.php`           | `ProjectResource.php`          |
| Model         | `{Entity}.php`                  | `Project.php`                  |
| Enum          | `{Domain}{Concept}.php`         | `TaskStatus.php`               |
| Event         | `{Subject}{Action}.php`         | `ProjectHealthUpdated.php`     |
| Listener      | `{Action}{Subject}.php`         | `SendPasswordUpdateEmail.php`  |
| Job           | `{Verb}{Subject}.php`           | `RecalculateProjectHealth.php` |
| Policy        | `{Model}sPolicy.php`            | `ProjectsPolicy.php`           |
| Trait         | `{Capability}.php`              | `RecordActivity.php`           |
| Query Builder | `{Model}QueryBuilder.php`       | `TaskQueryBuilder.php`         |
| Rule          | `{ValidationName}.php`          | `ActiveProjectMember.php`      |
| Notification  | `{Subject}{Action}.php`         | `TaskDue.php`                  |
| Interface     | `{Service}.php`                 | `Zoom.php`                     |
| Test          | `{Subject}Test.php`             | `ProjectFeatureTest.php`       |

### Import Order

```php
<?php

declare(strict_types=1);

namespace App\Services;

// 1. PHP built-in classes
use Exception;
use Throwable;

// 2. Vendor/Framework classes
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// 3. App classes (alphabetical)
use App\Actions\ProjectMetrics\ProjectHealthMetricAction;
use App\Events\ProjectHealthUpdated;
use App\Models\Project;
use App\Repository\ProjectRepository;
```

---

## Version History

| Version | Date       | Changes                                    |
| ------- | ---------- | ------------------------------------------ |
| 1.0.0   | 2026-01-22 | Initial guidelines extracted from codebase |

---

> **Note**: These guidelines are living documentation. Update them as patterns evolve.
