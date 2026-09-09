<laravel-boost-guidelines>
=== .ai/backend-guidelines rules ===

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

├── Services\Api\V1\            # Versioned services

│   ├── Dashboard\              # Feature-specific services

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

---

## 2. Actions

### Purpose

Actions are single-responsibility classes that encapsulate specific business logic operations.

### Location

`app/Actions/` or `app/Actions/{Domain}/`

### Guidelines

- ✅ Inject dependencies via constructor
- ✅ Keep actions focused on a single responsibility
- ✅ Use other actions as dependencies for composition
- ❌ Do not extend base classes
- ❌ Do not handle HTTP concerns (that's for controllers)

---

## 3. Services

### Purpose

Services orchestrate business logic, coordinating between repositories, actions, and external integrations.

### Location

`app/Services/Api/V1/` or `app/Services/Api/V1/{Feature}/`

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

### Guidelines

- ✅ Return typed collections (`Collection`, `Builder`, model types)
- ✅ Use `private` helper methods for reusable query logic
- ✅ Method prefixes: `get`, `find`, `filter`, `search`, `count`
- ❌ Do not include business logic (that belongs in Services/Actions)
- ❌ Do not fire events from repositories

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
- ✅ Use method injection for Request and Service dependencies
- ✅ Use `$this->authorize()` for policy checks
- ✅ Use `$request->validated()` or `$request->safe()` for clean data
- ✅ Use `#[Endpoint(operationId: 'resource.action')]` attribute for stable operation IDs in generated OpenAPI specs
- ✅ Tags are resolved centrally by `ScrambleServiceProvider`; do not manually add `@tags` to public controllers
- ✅ Return JSON with `data` as the canonical payload envelope for resource responses and `{ "message": string }` for command-only responses
- ✅ Error responses are documented only when inferred or explicitly ensured by the provider; do not claim all error statuses are automatically injected
- ❌ Do not put business logic in controllers
- ❌ Do not access database directly (use services/repositories)

### Response Status Codes

| Action | Status Code | Method                         |
| ------ | ----------- | ------------------------------ |
| Create | 201         | `response()->json([...], 201)` |
| Read   | 200         | `response()->json([...])`      |
| Update | 200         | `response()->json([...])`      |
| Delete | 200         | `response()->json([...])`      |
| Error  | 4xx/5xx     | `$this->respondError(...)`     |

---

## 7. Form Requests

### Purpose

Form Requests handle validation and authorization for incoming HTTP requests.

### Location

`app/Http/Requests/Api/V1/`

### Guidelines

- ✅ MUST implement a `toDto()` method to transform validated data into a strict DTO (e.g., `return ProjectData::fromValidated($this->validated());`)
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

### Testing Guidelines

- ✅ Use `/** @test */` annotation or `test_` prefix
- ✅ Method naming: `snake_case` describing behavior
- ✅ Follow AAA pattern: Arrange, Act, Assert
- ✅ Use `RefreshDatabase` trait for database tests
- ✅ Mock external dependencies with Mockery
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

// Delete (200 with message)
return $this->respondWithMessage('Resource deleted successfully.');
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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.29
- laravel/framework (LARAVEL) - v11
- laravel/pennant (PENNANT) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- larastan/larastan (LARASTAN) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v11
- rector/rector (RECTOR) - v1
- laravel-echo (ECHO) - v1
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3
- vue (VUE) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pennant-development` — Manages feature flags with Laravel Pennant. Activates when creating, checking, or toggling feature flags; showing or hiding features conditionally; implementing A/B testing; working with @feature directive; or when the user mentions feature flags, feature toggles, Pennant, conditional features, rollouts, or gradually enabling features.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
  - <code-snippet>public function \_\_construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v11 rules ===

# Laravel 11

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel 11 file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the Laravel 11 structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
  - Middleware registration is in `app/Http/Kernel.php`
  - Exception handling is in `app/Exceptions/Handler.php`
  - Console commands and schedule registration is in `app/Console/Kernel.php`
  - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11:
  - `php artisan make:enum`
  - `php artisan make:class`
  - `php artisan make:interface`

=== pennant/core rules ===

# Laravel Pennant

- This application uses Laravel Pennant for feature flag management, providing a flexible system for controlling feature availability across different organizations and user types.
- IMPORTANT: Always use `search-docs` tool for version-specific Pennant documentation and updated code examples.
- IMPORTANT: Activate `pennant-development` every time you're working with a Pennant or feature-flag-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== saloonphp/laravel-plugin rules ===

## SaloonPHP

- SaloonPHP is a PHP library for building beautiful, maintainable API integrations and SDKs with a fluent, expressive API.
- Uses a connector-based architecture where **Connectors** define the base URL and shared configuration, and **Requests** represent specific API endpoints.
- **Version Support**: SaloonPHP v2 and v3 are both actively supported. Check `composer.json` to determine which version-specific documentation to reference.
- Always use Artisan commands to generate SaloonPHP classes: `php artisan saloon:connector`, `php artisan saloon:request`, `php artisan saloon:response`, `php artisan saloon:plugin`, `php artisan saloon:auth`.
- Documentation: `https://docs.saloon.dev`
- **Before implementing features, use the `web-search` tool to get the latest docs. The docs listing is available in <available-docs>**

### Key Concepts

- **Connectors**: Extend `Saloon\Http\Connector`, define base URL via `resolveBaseUrl()`, use constructor property promotion for dependencies, override `defaultHeaders()` and `defaultAuth()`.
- **Requests**: Extend `Saloon\Http\Request`, set `$method` using `Saloon\Enums\Method` enum, override `resolveEndpoint()`, `defaultQuery()`, `defaultHeaders()`, `defaultBody()`.
- **Sending**: `$connector->send($request)` returns a response with methods like `json()`, `body()`, `status()`, `isSuccess()`, `dto()`, `dtoOrFail()`.
- **Body Types**: Implement `HasBody` interface and use traits: `HasJsonBody`, `HasXmlBody`, `HasMultipartBody`, `HasFormBody`, `HasStringBody`, `HasStreamBody`.
- **Authentication**: Use `TokenAuthenticator`, `BasicAuthenticator`, `QueryAuthenticator`, or implement `Saloon\Contracts\Authenticator`.
- **Plugins**: Traits that add reusable functionality. Built-in: `AcceptsJson`, `AlwaysThrowOnErrors`, `HasTimeout`, `HasRetry`, `HasRateLimit`, `WithDebugData`, `DisablesSSLVerification`, `CastsToDto`.
- **Middleware**: Use `middleware()->onRequest()` and `middleware()->onResponse()`, or implement `boot()` method.
- **DTOs**: Implement `createDtoFromResponse()` in request classes, use `$response->dto()` or `$response->dtoOrFail()`.

### Laravel Integration

- **Artisan Commands**: `saloon:connector`, `saloon:request`, `saloon:response`, `saloon:plugin`, `saloon:auth`, `saloon:list`.
- **Facade**: Use `Saloon\Laravel\Facades\Saloon` facade for mocking: `Saloon::fake([RequestClass::class => MockResponse::make(...)])`.
- **Events**: `SendingSaloonRequest` and `SentSaloonRequest` events are emitted during request lifecycle.
- **HTTP Client Sender**: Use `Saloon\Laravel\HttpSender` to integrate with Laravel's HTTP client (enables Telescope recording). Configure in `config/saloon.php`: `'default_sender' => \Saloon\Laravel\HttpSender::class`.
- **File Structure**: Check `config/saloon.php` for `integrations_path` setting. Default is `app/Http/Integrations`. Store connectors/requests in `{integrations_path}/{ServiceName}/` directory.

### Version Notes (v3)

- Global retry system: Set `$tries`, `$retryInterval`, `$useExponentialBackoff` properties directly on connectors/requests.
- Pagination is now a separate installable plugin (required for pagination features).
- Enhanced PSR-7 support with new response methods.

<available-docs>

## Upgrade

- [https://docs.saloon.dev/upgrade/whats-new-in-v3] Use these docs to understand what's new in SaloonPHP v3
- [https://docs.saloon.dev/upgrade/upgrading-from-v2] Use these docs for upgrading from SaloonPHP v2 to v3

## The Basics

- [https://docs.saloon.dev/the-basics/installation] Use these docs for installation instructions, Composer setup, and initial configuration
- [https://docs.saloon.dev/the-basics/connectors] Use these docs for creating connectors, setting base URLs, default headers, and shared configuration
- [https://docs.saloon.dev/the-basics/requests] Use these docs for creating requests, defining endpoints, HTTP methods, query parameters, and request bodies
- [https://docs.saloon.dev/the-basics/authentication] Use these docs for authentication methods including token, basic, OAuth2, and custom authenticators
- [https://docs.saloon.dev/the-basics/request-body-data] Use these docs for sending body data in requests, including JSON, XML, and multipart form data
- [https://docs.saloon.dev/the-basics/sending-requests] Use these docs for sending requests through connectors, handling responses, and request lifecycle
- [https://docs.saloon.dev/the-basics/responses] Use these docs for handling responses, accessing response data, status codes, and headers
- [https://docs.saloon.dev/the-basics/handling-failures] Use these docs for handling failed requests, error responses, and using AlwaysThrowOnErrors trait
- [https://docs.saloon.dev/the-basics/debugging] Use these docs for debugging requests and responses, using the debug() method, and inspecting PSR-7 requests
- [https://docs.saloon.dev/the-basics/testing] Use these docs for testing Saloon integrations, mocking requests, and writing assertions

## Digging Deeper

- [https://docs.saloon.dev/digging-deeper/data-transfer-objects] Use these docs for casting API responses into DTOs, creating DTOs from responses, implementing WithResponse interface, and using DTOs in requests
- [https://docs.saloon.dev/digging-deeper/building-sdks] Use these docs for building SDKs with Saloon, creating resource classes, and organizing API integrations
- [https://docs.saloon.dev/digging-deeper/solo-requests] Use these docs for creating standalone requests without connectors using SoloRequest class
- [https://docs.saloon.dev/digging-deeper/retrying-requests] Use these docs for implementing retry logic with exponential backoff and custom retry strategies (v3 includes global retry system at connector level)
- [https://docs.saloon.dev/digging-deeper/delay] Use these docs for adding delays between requests to prevent rate limiting and server overload
- [https://docs.saloon.dev/digging-deeper/concurrency-and-pools] Use these docs for sending concurrent requests using pools, managing multiple API calls efficiently, and asynchronous request handling
- [https://docs.saloon.dev/digging-deeper/oauth2-authentication] Use these docs for OAuth2 authentication flows including Authorization Code Grant, Client Credentials, and token refresh
- [https://docs.saloon.dev/digging-deeper/middleware] Use these docs for creating and using middleware to modify requests and responses, request lifecycle hooks, and boot methods
- [https://docs.saloon.dev/digging-deeper/psr-support] Use these docs for PSR-7 and PSR-17 support, accessing PSR requests and responses, and modifying PSR-7 requests

## Installable Plugins

- [https://docs.saloon.dev/installable-plugins/pagination] Use these docs for the Pagination plugin to handle paginated API responses with various pagination methods (required in v3, optional in v2)
- [https://docs.saloon.dev/installable-plugins/laravel-integration] Use these docs for Laravel plugin features including Artisan commands, facade, events, and HTTP client sender
- [https://docs.saloon.dev/installable-plugins/caching-responses] Use these docs for the Caching plugin to cache API responses and improve performance
- [https://docs.saloon.dev/installable-plugins/handling-rate-limits] Use these docs for the Rate Limit Handler plugin to prevent and manage rate limits
- [https://docs.saloon.dev/installable-plugins/sdk-generator] Use these docs for the Auto SDK Generator plugin to generate Saloon SDKs from OpenAPI files or Postman collections
- [https://docs.saloon.dev/installable-plugins/lawman] Use these docs for the Lawman plugin, a PestPHP plugin for writing architecture tests for API integrations
- [https://docs.saloon.dev/installable-plugins/xml-wrangler] Use these docs for the XML Wrangler plugin for modern XML reading and writing with dot notation and XPath queries
- [https://docs.saloon.dev/installable-plugins/building-your-own-plugins] Use these docs for building custom plugins (traits), creating boot methods, and extending Saloon functionality
  </available-docs>
  </laravel-boost-guidelines>
