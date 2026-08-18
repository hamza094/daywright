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
19. [Exceptions & Error Handling](#19-exceptions--error-handling)
20. [Logging & Operational Debuggability](#20-logging--operational-debuggability)
21. [Testing](#21-testing)
22. [API Response Standards](#22-api-response-standards)
23. [API Security & Authorization](#23-api-security--authorization)

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
- ✅ Accept explicit domain inputs (models, scalars, and DTOs for complex data); avoid untyped arrays
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
- ✅ Accept models, scalars, or strongly typed DTOs for complex payloads; avoid untyped arrays. Pass the acting user explicitly when needed
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

- ✅ MUST use `final readonly class` to ensure absolute immutability
- ✅ Define static constructors like `fromValidated(array $validated): self` and `fromArray(array $payload): self`
- ✅ Provide default values for optional fields and use nullable types with `?` syntax
- ✅ Include `toArray()` method for serialization when passing data to external integrations or jobs
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
- ✅ Use `$request->toDto()` to transform incoming data into strict typed DTOs
- ✅ Pass strongly typed DTOs downstream instead of `$request->validated()` arrays or the whole request
- ✅ Resolve the authenticated user in the controller and pass it explicitly to services or actions when needed
- ✅ Call one main collaborator per endpoint. In most cases that collaborator is a service; call an action directly only for tiny isolated operations
- ✅ Add docblocks for API documentation using Scramble attributes (`#[Endpoint]`, `#[ScrambleResponse]`)
- ✅ Document critical behaviors that aren't obvious from code: cascades, preconditions, side effects, irreversibility warnings
- ✅ Document parameter context (defaults, filter flags) when Scramble can't infer them from Form Request validation rules
- ✅ Document default `per_page` values since they're set in request methods (`perPageValue()`), not in validation rules
- ✅ Avoid redundant documentation that Scramble can infer from Form Request validation rules (e.g., file types, max sizes, filter flags)
- ✅ Use `@operationId` attribute for stable operation IDs in generated OpenAPI specs
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

## 19. Exceptions & Error Handling

### Purpose

Provide a unified, secure, and developer-friendly approach to throwing and rendering API errors.

### Location

- Base Handler: `app/Exceptions/Handler.php`
- Exception Registration: `app/Exceptions/Traits/HandlesApiExceptions.php`
- Formatting Logic: `app/Exceptions/Support/ApiErrorFormatter.php`
- Custom Exceptions: `app/Exceptions/` or `app/Exceptions/{Integration}/`

### Guidelines

- ✅ Extend `App\Exceptions\ApiException` for custom business logic exceptions. It enforces `status()`, `errorCode()`, and `publicMessage()`.
- ✅ Render all API exceptions using `ApiErrorFormatter::response()` to guarantee a strict JSON shape (`message`, `code`, `errors`, `meta`).
- ✅ Register new exception renderables inside `HandlesApiExceptions` trait.
- ✅ **Registration Order Matters:** Specific child exceptions (e.g., `ThrottleRequestsException`) MUST be registered before their generic parents (e.g., `HttpException`).
- ✅ Prevent Sensitive Data Leaks: Raw `Exception` or `Throwable` messages must NOT be exposed to the user in production. `ApiErrorFormatter::publicMessage()` acts as a defense-in-depth gatekeeper against SQL leaks and stack traces.
- ✅ Log 5xx system errors and unexpected issues; do NOT log 4xx client errors (like Validation, 404s, or Authentication errors) to prevent noise in the log stream.
- ❌ Do not return raw HTTP responses manually when an exception can communicate the failure more cleanly.

---

## 20. Logging & Operational Debuggability

### Purpose

Ensure the application is 100% "2 AM Debuggable". When production breaks, system logs must provide the exact context needed to diagnose without reproducing locally.

### Location

- Global logging config: `config/logging.php`
- Context/Redaction logic: `app/Logging/`

### Guidelines

- ✅ **Preserve Stack Traces**: Always pass the full `$exception` object to Monolog (e.g., `Log::error('msg', ['exception' => $e])`), NEVER serialize it as strings via `$e->getMessage()` or `$e->getTraceAsString()`.
- ✅ **Wrap External Boundaries**: All third-party API SDK calls (e.g., Vonage, Paddle) must be wrapped in `try/catch`. Log the failure with context before re-throwing. Do not let SDK exceptions bubble up silently.
- ✅ **Protect Loops in Commands**: When processing chunks in Console Commands, wrap the inner loop logic in a `try/catch`. A single corrupt row must never crash the entire cron job silently. Log the error and `continue`.
- ✅ **Log Silent Early Returns**: In queue jobs, if a required model is missing (e.g., deleted before job runs), log a warning/error before `return;`. Do not fail silently. (Exception: pure idempotency checks).
- ✅ **Redact Sensitive Data**: Use `ScrubSensitiveData` taps to prevent passwords and PII from leaking into logs. **Warning:** Do not log raw SQL bindings (e.g. `$query->bindings`), as they are indexed arrays and bypass key-based scrubbers.
- ✅ **Use JSON Formatting**: Always use `JsonFormatter` in production log channels (e.g., `daily`) to ensure structured, queryable logs.
- ❌ **No Happy Path Noise**: Do not log successful CRUD state changes or audit trails in the system operational logs. Keep system logs focused strictly on errors, failures, and system state anomalies.

---

## 21. Testing

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

## 22. API Response Standards

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

All API errors return a strict JSON payload defined by `ApiErrorFormatter`.

```json
// Validation Error (422)
{
    "message": "Validation failed.",
    "code": "validation_error",
    "errors": {
        "field_name": ["Error message here."]
    },
    "meta": {}
}

// Rate Limited (429) - Note: also returns Retry-After HTTP Headers
{
    "message": "Too many requests. Please try again later.",
    "code": "rate_limited",
    "errors": {},
    "meta": {
        "retry_after_seconds": 47
    }
}

// Server Error (500) - Masks sensitive leak data
{
    "message": "An unexpected server error occurred.",
    "code": "internal_server_error",
    "errors": {},
    "meta": {}
}
```

---

## 23. API Security & Authorization

### Purpose

Define the architecture and strict rules for authenticating, authorizing, and rate-limiting users and API tokens via Laravel Sanctum to maintain a watertight zero-trust architecture.

### Client Authentication Types

- **SPA / Internal Clients**: Authenticate via stateful session cookies (`web` guard). Sanctum issues `TransientToken`s that implicitly pass all scope checks.
- **Official Mobile App**: Authenticates via `Bearer` token with wildcard `*` abilities. Passes `firstParty.auth` but is blocked by `session.auth`.
- **Developer API Keys (Third-Party)**: Authenticate via `Bearer` tokens with explicit scopes. Blocked by both `session.auth` and `firstParty.auth`. Only passes `tokenAbility:` checks.

### Token Lifecycle Rules

All Sanctum Personal Access Tokens MUST follow these lifecycle constraints:

- ✅ **No Infinite Tokens**: Every token MUST have an `expires_at` date. Never allow `null` expiration in production.
- ✅ **Maximum Expiry Ceiling**: Enforce a 1-year absolute maximum in `ApiTokenService::createForUser()`. Cap any user-requested expiry to this ceiling.
- ✅ **Default Expiry**: If no expiration is specified, default to 90 days for developer PATs and 30 days for login/mobile tokens.
- ✅ **Multiple Active Tokens**: Users should be allowed to create multiple active tokens with descriptive names (e.g., "Zapier Prod", "CI/CD Pipeline") for isolated revocation.
- ✅ **Instant Revocation**: Deleting a token from the `personal_access_tokens` table is an immediate kill switch — Sanctum validates against the database on every request.
- ❌ **No Wildcard Tokens for Developers**: Only official mobile app login flows may issue `*` (wildcard) ability tokens. User-created developer tokens MUST have explicit, scoped abilities.

### First-Party Only & Admin Routes

Certain application boundaries MUST be strictly isolated from third-party developer API keys to prevent abuse. These routes must use either `session.auth` (Web SPA only) or `firstParty.auth` (SPA + Mobile).

**Always hide the following from third-party API keys:**

- **Admin Panel Operations**: Bulk actions, system configuration, global user impersonation.
- **Token Management (CRUD)**: API keys must NEVER be allowed to create, view, or delete other API keys.
- **Account Security**: Password changes, 2FA enablement/disablement, email address changes.
- **Billing & Subscriptions**: Upgrading/downgrading plans, viewing invoices, managing payment methods.
- **Account Deletion**: Deleting the entire workspace or user account.

### Scope Enforcement (Principle of Least Privilege)

DayWright uses a predefined, strict list of domain-specific scopes (e.g., `projects:read`, `team:write`). When routing, strictly adhere to the following rules:

- ✅ **No Over-Privileging**: A `GET` (read-only) route MUST NOT demand a `:write` scope. If a user only needs to read data, their read-only token must work.
- ✅ **No Domain Bleeding**: A route must only require the scope for the specific data domain it touches (e.g., a dashboard endpoint returning tasks must require `projects:read`, not `account:read`).
- ✅ **Strict Mutation Protection**: Every `POST`, `PUT`, `PATCH`, and `DELETE` route MUST be guarded by a `:write` scope to prevent read-only tokens from mutating data.
- ✅ **Prevent Privilege Escalation**: API keys (PATs) that create other API keys MUST only be allowed to grant a subset of their own scopes. Only SPA sessions (`TransientToken`) or tokens with wildcard `*` abilities can freely assign scopes.
- ✅ **Use custom middleware**: Always use the custom `tokenAbility:` middleware for scope checks. It gracefully bypasses scope checks for SPA session requests while enforcing them strictly for API keys.
- ✅ **API Resources**: Use `->middlewareFor()` when declaring `Route::apiResource()` to independently scope `index`/`show` (read) vs `store`/`update`/`destroy` (write).

### 4-Layer Rate Limiting Architecture

To protect against abuse and resource starvation, enforce Portkey-style multi-layered rate limits strictly:

- ✅ **Layer 0 (Global Safety Net)**: Broad IP-based limits (e.g., `300/min`) for unauthenticated routes. Must run early in the middleware stack.
- ✅ **Layer 1 (User Ceiling)**: Aggregate limits for a single authenticated user (e.g., `200/min`) across all their devices and tokens. Protects the global application from noisy neighbors.
- ✅ **Layer 2 (Per-Token Ceiling)**: Sub-limits for individual API keys (e.g., `30/min`). **Crucial invariant:** `(Per-Token Limit × Max Tokens) < User Ceiling` MUST always hold true to guarantee web dashboard headroom for the user. SPA requests (`TransientToken`) bypass this layer.
- ✅ **Layer 3 (Sensitive Mutations)**: Strict, isolated limits (e.g., `5/min` to `10/min`) on high-value endpoints like token creation/deletion, destructive actions (`DELETE` routes, force-deletes, member removals), and billing operations.

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
