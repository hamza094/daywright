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
├── Services\                   # Application services (not API-versioned)
│   ├── Auth\
│   ├── Dashboard\
│   ├── Project\
│   ├── Subscription\
│   └── Task\
└── Traits\                     # Reusable traits
```

### Naming Conventions

Use the File Naming Patterns table in the Quick Reference Card for all naming conventions. Do not introduce new patterns without team approval.

### Code Style & Conventions

- Docblocks: only add when needed (description, generics, array shapes). For iterables, use key/value generics (e.g., `Collection<int, User>`); for fixed arrays, use array-shape notation.
- Strings: prefer interpolation over concatenation.
- Let code "breathe" - avoid cramped formatting

## Comments

- **Avoid comments** - write expressive code instead
- When needed, use proper formatting:

  ```php
  // Single line with space after //

  /*
   * Multi-line blocks start with single *
   */
  ```

- Refactor comments into descriptive function names

### Migrations

- Do not write down methods in migrations, only up methods

### Routing & Channels

Routing rules aim for clarity and consistency. Follow these concise guidelines when adding or updating routes:

- Versioning: always include an API version prefix (for example, `/api/v1`). Group routes per version file.
- Route types: keep responsibilities separate — use `web` for the SPA and session-based flows, `api` for JSON endpoints, `console` for artisan commands, and `channels` for broadcast auth.
- Authentication: prefer `auth:sanctum` for stateful requests; use `guest`/`guest:api` for public auth endpoints (login/register).
- Throttling: add rate limits to sensitive endpoints (login, register, password, OAuth, invitation flows) using named throttle middleware.
- Two‑factor: place session-dependent two-factor endpoints under `web`; manage and protected endpoints belong under `auth:sanctum` and can require `2fa.enabled`.
- Resource routing: prefer `Route::apiResource()` for standard CRUD; add explicit routes for non-standard actions (restore, archive, export) and use `withTrashed()` only when needed.
- Nesting & scope: use `scopeBindings()` to enforce parent-child scoping; avoid `withoutScopedBindings()` except with clear intent and justification.
- Authorization: enforce access at the route level with `can:` middleware and keep policy logic inside policy classes.
- Grouping & naming: group related endpoints with `Route::controller()` + `prefix` and apply `name`/`as` conventions for predictable route names.
- Broadcast channels: authorize in channel callbacks (use policies or `can('access', $project)`); return `bool` or the authenticated user consistently.
- Fallbacks: API routes should return a JSON 404 fallback; reserve the SPA catch-all route for `web` and place it last.
- Misc middleware: use middleware for subscription checks, owner/role checks, and tracking (e.g., `subscription`, `can:owner`, custom middlewares); document custom middleware usage near the route declaration.

### Backend Layer Boundaries

Use this decision guide before adding a new class:

| Layer      | Owns                                      | Accepts                                                            | Returns                                                                | Must Not Do                                                                                                          |
| ---------- | ----------------------------------------- | ------------------------------------------------------------------ | ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Controller | One HTTP endpoint boundary                | Form Request / Request, route models, injected services or actions | `JsonResponse`, API resource, API resource collection                  | Own transactions, orchestrate multiple domain steps, use `app()`, pass the whole request deeper into the domain      |
| Service    | One application use case or read workflow | Models, scalars, arrays, DTOs, explicit acting user when needed    | Models, collections, paginators, arrays, DTOs, booleans, value objects | Read `request()` / `auth()` in non-auth services, return HTTP responses or resources                                 |
| Action     | One focused domain step                   | Models, scalars, arrays, DTOs                                      | Model, array, bool, primitive, value object                            | Behave like a controller or service, accept Request objects, return HTTP responses or resources                      |
| Repository | Query and data-access reuse               | Models, scalars, filter arrays, DTOs                               | Builder, models, collections, paginators, arrays, DTOs                 | Own business workflows, mutate as the main use-case boundary, read auth/request state, return resources or responses |

API versioning lives at the HTTP boundary. Version controllers, requests, and resources. Keep services, actions, and repositories under their domain namespaces unless a documented exception is truly required.

If a class mainly filters an in-memory collection, coordinates collaborators, or shapes API output, it should not keep a `Repository` name.

---

## 2. Actions

### Purpose

Actions are single-responsibility classes that encapsulate specific business logic operations.

### Location

`app/Actions/` or `app/Actions/{Domain}/`

### Guidelines

- ✅ Default public entrypoint: `execute()`
- ✅ Inject dependencies via constructor
- ✅ Keep actions focused on a single responsibility
- ✅ Accept explicit domain inputs (models, scalars, arrays, DTOs)
- ✅ Return domain results (model, bool, array, primitive, value object)
- ✅ Use other actions as dependencies for composition
- ✅ Create an action when the step is a named domain operation, reused in more than one flow, or isolates an integration / side effect
- ✅ Controllers may call an action directly only for tiny isolated operations; otherwise actions should sit under a service
- ❌ Do not extend base classes
- ❌ Do not handle HTTP concerns (that's for controllers)
- ❌ Do not accept `Request` / Form Request objects or return API resources / `JsonResponse`
- ❌ Do not turn an action into a mini-service that coordinates an entire endpoint flow

---

## 3. Services

### Purpose

Services orchestrate one application use case or read workflow, coordinating between repositories, actions, and external integrations.

### Location

`app/Services/` or `app/Services/{Domain}/`

### Guidelines

- ✅ Use `final readonly` for immutable services
- ✅ Name services by workflow or responsibility; avoid vague names such as `FeatureService`, `HelperService`, or `ManagerService`
- ✅ Own one use case boundary or one read/listing/composition workflow boundary
- ✅ Wrap multi-step operations in `DB::transaction()`
- ✅ Use PHPDoc for array parameter types: `@param array<string, mixed>`
- ✅ Accept models, scalars, arrays, or DTOs; pass the acting user explicitly when needed
- ✅ Coordinate actions, repositories, transactions, notifications, domain events, and external integrations
- ✅ Keep cohesive orchestration in the service; do not extract a new action for one-off glue code that is only used inside that service
- ✅ Extract an action only when the step is clearly named in the domain, independently testable, reused, or integration-heavy
- ✅ Fire events after successful operations
- ✅ Dispatch notifications from services
- ✅ Return models, collections, paginators, arrays, DTOs, or value objects and let controllers shape HTTP responses
- ❌ Do not access `Request` directly (pass data as parameters)
- ❌ Do not accept Form Request objects in non-auth/session services
- ❌ Do not call `request()`, `auth()`, or `Auth::user()` in non-auth/session services
- ❌ Do not return HTTP responses

Auth or session oriented services are the narrow exception. They may touch request or auth state only when that coupling is their actual job.

---

## 4. Repositories

### Purpose

Repositories encapsulate data access logic, providing a clean API for querying the database.

### Location

`app/Repository/`

### Guidelines

- ✅ Keep repositories query-only and data-access focused
- ✅ Return typed collections (`Collection`, `Builder`, model types)
- ✅ Accept explicit models, scalars, filter arrays, or DTOs
- ✅ Use `private` helper methods for reusable query logic
- ✅ Method prefixes: `get`, `find`, `filter`, `search`, `count`
- ❌ Do not include business logic (that belongs in Services/Actions)
- ❌ Do not own create / update / delete workflows or cross-entity orchestration
- ❌ Do not fire events from repositories
- ❌ Do not return API resources or HTTP responses
- ❌ Do not accept `Request` objects or read `Auth` / `auth()` state
- ❌ If a class mainly filters already-loaded collections, coordinates collaborators, or shapes API payloads, move or rename it out of `Repository`

---

## 5. Data Transfer Objects (DTOs)

### Purpose

DTOs are immutable value objects for transferring data between layers with type safety.

### Location

`app/DataTransferObjects/{Domain}/`

### Guidelines

- ✅ Use `final` class modifier
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

### Hierarchy

```
Controller (base)
└── ApiController
    └── V1 Controllers
```

### Guidelines

- ✅ Extend `ApiController` for API endpoints
- ✅ Keep controllers as HTTP boundaries: validate, authorize, resolve actor, delegate, and return the response
- ✅ Use method injection for Request and Service dependencies
- ✅ Prefer resource controllers for canonical CRUD and invokable controllers for one-off commands or queries
- ✅ Use `$this->authorize()` for policy checks
- ✅ Use `$request->validated()` or `$request->safe()` for clean data
- ✅ Pass validated arrays or explicit request accessors downstream instead of the whole request
- ✅ Resolve the authenticated user in the controller and pass it explicitly to services or actions when needed
- ✅ Call one main collaborator per endpoint. In most cases that collaborator is a service; call an action directly only for tiny isolated operations
- ✅ Add docblocks with `@operationId` and `@tags` for API documentation
- ✅ Return JSON with `message` + `resource` pattern
- ❌ Do not put business logic in controllers
- ❌ Do not coordinate multi-step workflows, transactions, or multiple domain collaborators in controllers
- ❌ Do not access database directly (use services/repositories)
- ❌ Do not use `app()` inside controllers
- ❌ Do not create duplicate feature-bucket controllers when a focused routed controller already owns the behavior

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

### Guidelines

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
├── TestCase.php               # Base test case
├── CreatesApplication.php     # Application bootstrapping
├── Feature/
│   ├── Api/
│   │   ├── Auth/             # Authentication, session, and OAuth endpoint tests
│   │   ├── Contracts/
│   │   │   ├── Docs/         # API documentation contract tests
│   │   │   └── Routes/       # Route registration and naming contract tests
│   │   ├── Middleware/       # API middleware behavior tests
│   │   ├── V1/
│   │   │   ├── Conversations/
│   │   │   ├── Dashboard/
│   │   │   ├── Meetings/
│   │   │   ├── Messages/
│   │   │   ├── Notifications/
│   │   │   ├── Projects/
│   │   │   ├── Subscriptions/
│   │   │   ├── Tasks/
│   │   │   └── Users/
│   │   └── Webhooks/         # Webhook endpoint tests grouped by provider
│   ├── Database/             # Database constraints and migration behavior tests
│   └── Exceptions/           # Exception handler and reporting tests
├── Unit/
│   ├── Actions/
│   ├── Enums/
│   ├── Models/
│   ├── Repository/
│   ├── Resources/
│   └── Services/
├── Fixtures/                  # Test fixtures (JSON, etc.)
├── Helpers/                   # Shared test helper classes
├── Support/                   # Test builders and scenario setup
└── Traits/                    # Reusable test traits
```

### Test File Naming

- **Default pattern**: `{Subject}Test.php`
- **Canonical acceptance files**: `{Feature}FeatureTest.php` only when a single file intentionally owns a broader feature boundary
- Class names must match file names
- Do not introduce `*Tests.php`, `Phase*Test.php`, placeholder names like `test_example`, or misspelled test file names

### Test Boundaries

- Feature tests are organized by external behavior first, not by implementation type
- Versioned endpoint tests belong under `tests/Feature/Api/V1/{Domain}/`
- Authentication, session, and OAuth flows belong under `tests/Feature/Api/Auth/`
- API contract tests belong under `tests/Feature/Api/Contracts/{Concern}/`
- Webhook endpoint tests belong under `tests/Feature/Api/Webhooks/{Provider}/`
- Do not create new permanent tests under `tests/Feature/Api/Controllers/` or `tests/Feature/Api/Services/`
- Unit tests instantiate subjects directly and belong under `tests/Unit/{Layer or Domain}/`
- Shared setup belongs in `tests/TestCase.php`, `tests/Traits/`, or `tests/Support/`

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

### Testing Guidelines

- ✅ Use `/** @test */` annotation or `test_` prefix
- ✅ Keep one clear subject per test file so the file is easy to skim
- ✅ Method naming: `snake_case` describing behavior
- ✅ Follow AAA pattern: Arrange, Act, Assert
- ✅ Use `RefreshDatabase` trait for database tests
- ✅ Mock external dependencies with Mockery
- ✅ Use `assertJsonValidationErrors()` for validation tests
- ✅ Define route constants for repeated route names
- ✅ Create setup traits for common test configuration
- ✅ Move repeated test-only setup into shared helpers instead of copying it across files
- ✅ Use `Http::preventStrayRequests()` to catch unmocked HTTP calls
- ❌ Do not group feature tests by controller or service implementation folder when the real boundary is a domain or endpoint

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
