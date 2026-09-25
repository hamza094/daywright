# Phase 3: Stabilize the public API contract

## Goal

Make the public v1 API behave consistently and make its OpenAPI document describe the behavior that Laravel actually serves. Fix only the behaviors in this phase. Do not redesign the API or rename resources.

Before changing code, inspect the current implementation. The audit and examples below identify known risks, but some items may already be fixed or may have changed. Confirm each behavior with the current routes, code, and tests. Preserve authorization, token abilities, first-party authentication, idempotency, throttling, and existing business rules.

Use the repository's test layout in `.ai/guidelines/backend-guidelines.md`. Prefer extending an existing relevant feature or contract test over creating a duplicate test suite.

## P3.1: Make update methods and no-op behavior clear

### Why this matters

Clients need to know whether an update changes only supplied fields or replaces the whole resource. A client retrying a request should not receive an error merely because the requested value is already stored.

### Inspect and change

1. Inspect `routes/api/v1/projects`, `routes/api/v1/projects/tasks.php`, `TaskUpdateRequest`, `ProjectUpdateRequest`, `StageRequest`, the task/project services, and state-transition code.
2. Confirm which methods the task and project update routes register. `apiResource` may register both `PUT` and `PATCH`.
3. These request DTOs represent partial updates. Use `PATCH` as the documented partial-update method. Keep `PUT` only if it truly replaces the full resource or a current client depends on it. If changing/removing `PUT`, check consumers and document the rollout decision in P3.6; do not silently break a client.
4. Remove checks that reject a project name, about text, or notes solely because the submitted value equals the stored value. Keep normal type, length, required-when-present, authorization, and state-transition validation.
5. A request that sets a state to its current state may succeed only when that state transition is allowed. It must not repeat notifications or other side effects. Continue rejecting transitions out of terminal states when the state machine forbids them.
6. Keep the existing `version` precondition for task/project edits. `version` is required for collaborative updates; it is not a business field. Document this requirement and coordinate deployed clients in P3.6.

### Tests

Through HTTP feature tests, prove:

- A partial update changes only supplied fields.
- Re-submitting the current project name/about/notes is accepted when the version is current and does not repeat side effects.
- A forbidden terminal-state transition remains rejected.
- The documented update method is registered and the OpenAPI document lists the same method.

## P3.2: Keep error bodies and protocol headers consistent

### Why this matters

Clients use the status, JSON body, and HTTP headers to decide whether to correct input, authenticate, retry, or use a different method. Dropping a header or changing `{}` to `[]` can break that handling.

### Inspect and change

1. Inspect `app/Exceptions/Traits/HandlesApiExceptions.php`, `ApiErrorFormatter`, authentication middleware responses, and existing exception tests.
2. For a `405 Method Not Allowed`, preserve the exception's `Allow` header when formatting the JSON error.
3. Preserve `Retry-After` and the current response behavior for rate-limited requests (`429`). Preserve headers from other HTTP exceptions when applicable.
4. Format authentication-boundary errors through `ApiErrorFormatter` so they use the same `{message, code, errors, meta}` envelope as other API errors.
5. When empty, `errors` and `meta` must serialize as JSON objects (`{}`), not arrays (`[]`). Check the raw JSON or decoded object type; ordinary path assertions may not catch this distinction.
6. A server-side failure must have a safe public message. Do not include stack traces, SQL, filesystem paths, credentials, or raw provider responses.

### Tests

Extend existing HTTP tests to prove a real unsupported-method request returns `405` with `Allow`, a rate-limit response retains `Retry-After`, empty `errors`/`meta` serialize as objects, and a forced server error has a safe body.

## P3.3: Preserve validated pagination settings in links

### Why this matters

When a client follows `next`, it should see the next page of the same authorized search with the same filters, sorting, and page size. A link that drops those values can return a different collection.

### Inspect and change

1. Inspect paginated project, meeting, and admin-user endpoints, their Form Requests, controllers/services, and current pagination tests.
2. Build links from validated, canonical query values only. Do not copy arbitrary request parameters into links.
3. Preserve applicable filters, sort, and `per_page`. The paginator should advance `page` itself; do not carry the current page value as an extra stale query parameter.
4. Do not change listings that already produce correct links. Fix another listing only if inspection or a test demonstrates the same defect.

### Tests

For each affected listing, test the generated `next` URL. Follow at least one generated link and confirm it returns the next page of the same authorized filtered/sorted collection, with the same page size.

## P3.4: Register only supported subscription routes

### Why this matters

HTML form routes such as `create` and `edit` are not useful in this JSON API. If Laravel registers them without working controller actions, callers may receive `500` responses. Hiding them from OpenAPI does not remove them from Laravel's route table.

### Inspect and change

1. Inspect `routes/api/v1/users.php` and the subscription controller.
2. Run `php artisan route:list` for the subscription routes. The route currently uses `Route::singleton(...)->creatable()`, which may register a `create` route. Confirm this in the actual route list.
3. Remove `->creatable()` if the API does not intentionally support a working `create` endpoint. Do not change the supported show/store/update/destroy operations.
4. Preserve each operation's existing authentication, idempotency, subscription, and throttle middleware.
5. Confirm neither `create` nor `edit` is registered or published in OpenAPI.

### Tests

Use a route-registration test or route-list inspection to prove only supported routes exist and the supported routes retain their middleware. Do not add a route refactor if the current route table already meets this requirement.

## P3.5: Report infrastructure failures as server failures

### Why this matters

`422` means the client sent invalid data. An S3 outage, network error, or database failure is a server problem; reporting it as `422` tells clients not to retry and wrongly blames their request.

### Inspect and change

1. Inspect `app/Services/FileService.php`, `app/Services/User/UserService.php`, their controllers, exception rendering, and existing tests.
2. Keep genuine invalid uploads and invalid passwords as validation errors.
3. Do not convert every storage or password-update exception into `ValidationException`. For infrastructure failures, report the exception safely and return the existing safe `5xx` API envelope. Do not expose exception text, secrets, or provider response bodies.
4. Preserve transaction and cleanup behavior. In particular, do not claim a stored file was uploaded successfully if the storage operation failed.
5. Add a focused failure-path test for each service only if existing tests do not already prove the distinction between invalid input and infrastructure failure.

## P3.6: Publish and coordinate the actual v1 contract

### Why this matters

Clients rely on the API's routes, request rules, responses, and OpenAPI document. If these disagree, clients can be built against behavior the server does not provide.

### Required decisions and work

1. Before changing/removing a v1 method or changing request/response meaning, identify whether the current frontend or another client uses it. Record the compatibility decision. Do not guess that a route has no consumers.
2. Explicitly document the required task/project `version` field and `409 edit_conflict` behavior introduced in Phase 2.5. Coordinate the backend release with clients that must send `version`; do not silently make the version optional.
3. Document the externally visible pending/unknown outcomes for Zoom and Paddle operations where those responses are part of public endpoints. Explain what clients should do, such as poll/retry with the same operation key or wait for reconciliation, based on the actual implementation. Do not promise automatic recovery where the code does not guarantee it.
4. Update source annotations or Scramble transformers for behavior changes. Let Scramble generate `api.json`; never hand-edit the generated file.
5. Run `php artisan scramble:export` and inspect the changed operations and schemas. Compare the generated contract with the actual Laravel routes and HTTP behavior.
6. Run the existing `ScrambleDocsTest` and `RuntimeOpenApiParityTest`. Extend them only for a real uncovered contract behavior.

## Deferred work

P3.7 naming changes for resources, bulk jobs, and two-factor status are deferred. Keep existing names stable in v1 unless a verified defect makes a name unusable or unsafe. If a rename is needed, first define a compatibility path (for example, support the old and new names during a transition, or introduce the change in a new API version). Do not make cosmetic renames as part of this phase.

## Completion checklist

Phase 3 is complete when all of the following are true:

- Each P3 item was checked against the current code; already-correct items were verified and left alone.
- Confirmed behavior gaps above have focused HTTP or route tests.
- Existing authorization, first-party boundaries, token abilities, idempotency, throttling, and terminal-state rules remain intact.
- The route table, runtime responses, and freshly generated `api.json` agree for affected endpoints.
- The OpenAPI docs/parity tests, `composer test`, and `composer stan` pass.
- The implementation report records changed files, commands and results, client compatibility decisions (especially required `version`), and any remaining limitation.

Phase 4 covers real database/Redis/worker concurrency, process interruption and recovery, provider sandbox flows, and performance evidence. Passing Phase 3 does not replace those production-readiness checks.
