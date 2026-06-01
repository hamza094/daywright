# API Response Standardization Plan

Created: 2026-05-02

This plan defines the target API response contract for DayWright and breaks the refactor into safe phases.
The goal is to make every API response predictable, resource-driven, and easy to document with Scramble.

Work through the phases in order. Do not start broad frontend rewrites until the backend response helpers and exception formatting are stable.

## Target Response Contract

1. Single resource

```json
{
  "data": { ... }
}
```

2. Collection (paginated)

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42
  },
  "links": {
    "first": "https://example.test/api/v1/resources?page=1",
    "last": "https://example.test/api/v1/resources?page=3",
    "next": "https://example.test/api/v1/resources?page=2",
    "prev": null
  }
}
```

3. Create / Update

```json
{
  "data": { ... }
}
```

4. Delete

```json
{
  "message": "Resource deleted successfully"
}
```

5. Error responses

```json
{
  "message": "Human readable message",
  "errors": { ... }
}
```

## Required Rules

- Do NOT include a `success` or `status` field in the response body.
- Always use HTTP status codes to indicate success or failure.
- Never return raw objects or raw resource collections without wrapping them in `data`.
- Do NOT mix multiple response styles across controllers.
- Use Laravel API Resources (`JsonResource`) for all model responses.
- Ensure pagination responses include both `meta` and `links`.
- Keep response structure consistent across all endpoints.

## Additional Rules

- Use `data` as the only top-level payload key for successful reads and writes.
- Do not call `JsonResource::withoutWrapping()`; rely on Laravel's default outer `data` wrapping for top-level resources.
- Do not disturb simple Laravel resource returns that already satisfy the contract.
- If an endpoint can simply return `new SomeResource($model)` or `SomeResource::collection(...)`, prefer that over wrapping it again in custom controller code.
- Keep any `ApiController` response helpers small, obvious, and readable; they should support the contract, not hide it.
- Use imported `Symfony\Component\HttpFoundation\Response` constants consistently as `Response::HTTP_*` anywhere an explicit status code is returned.
- Use Laravel's native paginated resource responses via `SomeResource::collection($paginator)` or a dedicated `ResourceCollection`; do not keep a custom pagination envelope builder.
- Use `message` only for delete responses and action-only responses that do not return a resource.
- Keep pagination metadata inside `meta`; do not add sibling keys like `projectsCount` or `usersCount`.
- Empty collections must keep the same shape as non-empty collections: `data`, `meta`, and `links` must still exist.
- Do not place transport-level envelope keys inside resources. Resources should serialize domain fields only.
- Domain fields named `status` are allowed inside `data` when they represent business data, not transport state.
- Use snake_case for JSON keys everywhere.
- Prefer `200 OK` for delete endpoints in this refactor because the contract requires a response body with `message`.
- Use `201 Created` for create endpoints.
- Use `202 Accepted` only when work is asynchronous and not finished yet.
- Use `204 No Content` only for endpoints that truly return an empty body.
- Move generic error formatting out of controllers and into the exception handler or shared response layer.
- Keep only truly domain-specific exception handling centralized.

## Why This Contract

This contract removes duplicate transport state from the body and lets HTTP status codes do their job.

Benefits:

- Clients do not need to inspect both the status code and a `success` or `status` flag.
- Controllers become simpler because they all use the same response helpers.
- Frontend code can read one predictable shape instead of branching on `user`, `users`, `projects`, `data`, `success`, or `status`.
- Tests become easier to write because the outer response contract is stable.
- Scramble can infer responses more reliably because all endpoints follow the same `data` or `message` pattern.

Implementation note:

- With `JsonResource::withoutWrapping()` removed, Laravel 11 will wrap the outermost resource in `data` by default.
- Paginated resource responses will also include `data`, `meta`, and `links` by default.
- Because of that, simple resource endpoints should usually return the resource directly and let Laravel shape the response naturally.
- Native Laravel pagination should replace `BuildPaginatedPayloadAction` during this refactor.
- This supports the target contract directly, but frontend code that wants a flatter value should normalize responses in a service or store layer rather than changing the API contract.

## Current Contract Break Points

These areas currently violate the target contract and must be handled carefully during the refactor:

### Transport flags in body

- `AuthPayload` currently includes a top-level `status` field.
- Two-factor endpoints return top-level `status` values such as `enabled`, `disabled`, and `in_progress`.
- Verification endpoints return top-level `status` messages.
- Some dashboard and meeting endpoints return top-level `success`.
- Webhook endpoints return `{ "status": "success" }`.

### Top-level payload key drift

- Some endpoints return `user` or `users`.
- Some endpoints return `project` or `projects`.
- Some endpoints return `tokens`.
- Some endpoints return `meetingsData`.
- Some endpoints return a raw `data` payload.

### Mixed empty and non-empty shapes

- Notification index returns `{ "message": ... }` when empty but a paginated payload when non-empty.
- Admin task index merges a `message` key into the payload only when empty.
- Some list endpoints also add separate count keys outside `meta`.

### Filtering contract drift

- Some endpoints use dedicated filter request classes while others validate query parameters inline or read directly from `request()`.
- Filter names and shapes vary between endpoints, for example standalone `search`, `filter`, booleans, and ad hoc parameters such as `request=archived`.
- Some query logic is applied at the database layer while other filters run on already loaded collections.
- Query composition and UI-facing filter labels are currently mixed together in some repository classes.

### Custom pagination payload builder

- `BuildPaginatedPayloadAction` duplicates a response shape Laravel already provides for paginated resources.
- Some list endpoints manually wrap paginator data instead of returning native paginated resource responses directly.

### Generic error formatting inside controllers

- Several controllers return `{ "error": ... }` directly.
- Other controllers return `{ "message": ... }` for errors.
- Some exception handlers already return richer error payloads.

### Envelope logic inside resources

- `ProjectInsightsResource` currently includes `success` and `message` even though resources should only describe domain data.

## Refactor Strategy

Use a phased migration to avoid breaking the frontend all at once.

### Phase 1 - Response Infrastructure and Shared Helpers

Priority: Critical

Goals:

- Introduce one shared API response layer.
- Freeze the target contract before changing many controllers.
- Give Scramble a stable pattern to infer.

Tasks:

- Add a small set of response helper methods to `ApiController` or a dedicated responder class.
- Keep Laravel's default resource wrapping enabled and document that it is part of the contract.
- Do not replace clear native resource returns such as `return new ProjectResource(...)` when they already match the target contract.
- Standardize on imported `Response::HTTP_*` constants in controllers, responders, and the exception handler whenever a status code must be set explicitly.
- Standardize helper methods for:
  - manual array responses wrapped in `data`
  - create/update responses when no direct resource return is used
  - delete/action message responses
- Use Laravel's native paginated resource responses directly via `SomeResource::collection($paginator)` or dedicated `ResourceCollection` classes.
- Do not add a replacement abstraction for pagination when Laravel already provides the target `data`, `meta`, and `links` structure.
- Prefer returning top-level `JsonResource` and resource collections directly when the endpoint only needs the standard `data` envelope.
- Use explicit `response()->json(...)` only when the endpoint needs a `message`, custom HTTP code handling, or non-resource payload composition.
- Keep helper names and behavior straightforward enough that a controller remains readable at a glance.
- Remove use of generic helper packages that inject `success` by default where they conflict with the target contract.
- Add examples and developer guidance for new controller code.

Exit criteria:

- New and refactored endpoints can be implemented without hand-building envelopes.
- Controllers that can naturally return a Laravel resource still do so.
- Explicit status codes use `Response::HTTP_*` constants consistently.
- Paginated endpoints no longer depend on `BuildPaginatedPayloadAction`.
- The shared helper layer never emits `success`, `status`, or `error` transport keys.

### Phase 2 - Centralize Generic Error Formatting

Priority: Critical

Goals:

- Move common API error formatting out of controllers.
- Keep only business-specific error semantics in dedicated exceptions.

Tasks:

- Standardize `Handler` responses to use:

```json
{
  "message": "...",
  "errors": { ... }
}
```

- Keep `errors` only for validation-like scenarios.
- Replace controller-level `{ "error": ... }` payloads with thrown exceptions or standard handler-driven responses.
- Preserve domain-specific exception details only where clients actually need them.
- Move specialized metadata into a nested `details` object when necessary instead of adding many root keys.

Exit criteria:

- Generic 400, 401, 403, 404, 405, 422, 429, and 500 responses share one format.
- Controllers stop returning ad hoc error envelopes.

### Phase 3 - Filtering Standardization

Priority: High

Goals:

- Make filtering predictable, composable, and consistent before broad read-endpoint rewrites.
- Keep filtering Laravel-native and avoid introducing a new query package unless current conventions still prove insufficient after standardization.

Tasks:

- Define one filter contract for list-style endpoints.
- Standardize on:
  - `filter[...]` for named filters
  - `sort` for sorting
  - `page` and `per_page` for pagination inputs where needed
- Keep standalone `search` only for dedicated autocomplete or lookup endpoints that are not general list filters.
- Give every filterable endpoint a dedicated `FormRequest` that validates and normalizes query input.
- Remove inline query validation from controllers for filterable endpoints.
- Stop reading from global `request()` inside repositories and services; pass validated filters or a DTO into the query layer.
- Push filtering into model query builders, scopes, repositories, or query objects so it stays composable and testable.
- Avoid filtering already loaded collections in memory when the same logic can be expressed in SQL.
- Separate query composition from UI-facing filter labels such as `applied_filters`.
- Normalize boolean, archived/trashed, and status filter semantics across domains.

High-risk areas in this phase:

- `ProjectRepository` activity filtering
- `TaskController` archived task filter
- `NotificationsController` list filter
- admin project and task filters
- dashboard project and user-task filters
- invitation and member search endpoints

Exit criteria:

- Filterable endpoints follow one documented query contract.
- No repository or service reaches into the global request to decide filters.
- Filter logic is applied at the query layer wherever practical.

### Phase 4 - Normalize Read Endpoints

Priority: High

Goals:

- Standardize all GET-style endpoints after the response and filter contracts are stable.

Tasks:

- Convert all single-resource responses to:

```json
{
  "data": { ... }
}
```

- Convert all paginated responses to:

```json
{
  "data": [ ... ],
  "meta": { ... },
  "links": { ... }
}
```

- Replace `BuildPaginatedPayloadAction` usage with native Laravel paginator resource responses.
- Remove alternate root keys such as `users`, `projects`, `tokens`, and `meetingsData`.
- Move extra counts into `meta`.
- Keep empty collections on the same shape with empty arrays and valid pagination metadata.
- Refactor resources that currently include envelope fields.

High-risk areas in this phase:

- `UserController`
- `ProjectController` index
- `NotificationsController` index
- admin list endpoints
- dashboard list/read endpoints
- meeting list/show endpoints

Exit criteria:

- All read endpoints use `data` consistently.
- No read endpoint changes shape when empty.

### Phase 5 - Normalize Create, Update, Delete, and Action Endpoints

Priority: High

Goals:

- Standardize all state-changing endpoints after the read contract is stable.

Tasks:

- Create and update endpoints must return:

```json
{
  "data": { ... }
}
```

- Delete endpoints must return:

```json
{
  "message": "Resource deleted successfully"
}
```

- Action-only endpoints without a returned resource should use a message-only response.
- Remove body-level `success` or `status` from auth, verification, 2FA, and integration flows.
- For action endpoints that currently use business state flags, move that state into `data` using a domain-specific key.

Example for 2FA state reads:

```json
{
  "data": {
    "two_factor_state": "enabled"
  }
}
```

Exit criteria:

- State-changing endpoints no longer use mixed success envelopes.
- Delete endpoints are uniform.

### Phase 6 - Frontend Adaptation

Priority: Critical

Goals:

- Prevent contract drift between backend changes and client consumers.

Tasks:

- Inventory every frontend consumer of API responses before each backend slice is merged.
- Update frontend query serialization to match the standardized filter contract.
- Update service/store/component code to read:
  - `response.data.data`
  - `response.data.meta`
  - `response.data.links`
  - `response.data.message`
- Remove frontend branches that depend on `success`, `status`, `error`, `users`, `projects`, or other legacy root keys.
- Where component ergonomics matter, normalize API responses inside frontend services so components can consume a flatter `payload` object without changing the backend contract.
- Update mocks and fixtures to the new contract.
- Add a normalization layer in frontend services only if required for incremental rollout; do not keep permanent compatibility shims longer than needed.

High-risk frontend areas:

- auth bootstrap and login flows
- two-factor and verification UIs
- project lists and dashboard widgets
- notifications stores and components
- admin tables and dashboard panels

Exit criteria:

- Frontend reads the new contract without compatibility hacks.
- Frontend query building matches the standardized filter contract.
- Legacy response-shape assumptions are removed.

### Phase 7 - Backend and Frontend Tests

Priority: Critical

Goals:

- Lock the new contract in place with executable checks.

Tasks:

- Update PHPUnit feature tests to assert the new outer contract.
- Add tests that cover the standardized filter contract, including validation, normalization, and query semantics.
- Add contract assertions for:
  - single resource endpoints
  - paginated endpoints
  - create/update endpoints
  - delete endpoints
  - validation and not-found errors
- Update frontend tests and mocks for the new response shape.
- Add regression tests for known break points such as empty collection responses and auth flows.

Exit criteria:

- Backend and frontend tests fail when an endpoint returns legacy envelope keys.

### Phase 8 - Scramble and Documentation Alignment

Priority: High

Goals:

- Make the contract easy for Scramble to infer and document.

Tasks:

- Ensure controller responses consistently use resources or arrays built from shared helpers.
- Avoid dynamic top-level key names in successful responses.
- Keep resource classes focused on domain fields only.
- Use explicit response annotations only where inference still needs help.
- Document the standardized filter contract alongside the response contract.
- Document the new contract for internal and external clients.

Exit criteria:

- Scramble can infer the vast majority of response envelopes automatically.
- Response docs no longer need many custom one-off examples.

## Recommended Implementation Order

Implement the refactor in this order to reduce frontend breakage:

1. Shared response helpers, `Response::HTTP_*` constants, and exception handler
2. Filtering standardization for list and search endpoints
3. Low-risk CRUD endpoints that already use resources cleanly
4. Read endpoints using native Laravel resource pagination
5. Auth, 2FA, verification, and reset-password endpoints
6. Frontend query and response cleanup
7. Final tests and Scramble alignment

## Definition of Done

The refactor is complete when all of the following are true:

- No controller returns top-level `success`, `status`, or `error` transport fields.
- No controller returns raw models, raw resource collections, or mixed root payload keys.
- Explicit status codes use `Response::HTTP_*` constants consistently.
- Successful single and create/update responses always use `data`.
- Paginated collections always include `data`, `meta`, and `links`.
- No endpoint depends on `BuildPaginatedPayloadAction` for pagination envelopes.
- Filterable endpoints use one documented, validated filter contract.
- Delete responses always use `message`.
- Generic error formatting is handled centrally.
- Frontend consumers and tests expect only the new contract.
- Scramble can infer stable response shapes without heavy manual overrides.

## Suggested First Slice

Start with a narrow vertical slice before the broad rollout:

- add shared response helpers
- update one filter request and one query path
- update one simple resource controller
- update one native paginated controller
- update one frontend consumer for each
- update the related backend and frontend tests

Recommended first slice candidates:

- `NotificationsController`
- `Admin\TaskController`
- `Admin\ProjectController`

These endpoints cover enum-backed filters, simple search/filter pagination, and more complex admin filtering without forcing auth-flow changes on day one.

Note: Always use ApiController responses as the single source of truth when not returning simply model resource
For Api controolers extend ApiController instead of Controller
