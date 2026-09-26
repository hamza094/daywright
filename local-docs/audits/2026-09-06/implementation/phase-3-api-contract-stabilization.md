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

### Plain-language objective

Make every public API error easy for the frontend to understand. The JSON body must use the application's standard shape, and HTTP instructions such as `Allow` and `Retry-After` must not be lost while converting an exception into JSON.

Do not redesign the exception system. Make the smallest changes in the existing handlers and preserve current status codes, error codes, authentication boundaries, and rate-limit behavior.

### Exact files to inspect

- `app/Exceptions/Traits/HandlesApiExceptions.php`
- `app/Exceptions/Support/ApiErrorFormatter.php`
- `app/Providers/RouteServiceProvider.php`
- Existing tests under `tests/Feature/Exceptions/` and `tests/Feature/Api/`

### Required implementation

1. Keep `ApiErrorFormatter` responsible for the JSON body. Do not create separate JSON formats inside individual exception handlers.

2. Update the `MethodNotAllowedHttpException` handler in `HandlesApiExceptions.php`. It currently calls `ApiErrorFormatter::response(...)` but discards the exception headers. Change it to:

   ```php
   return ApiErrorFormatter::response(
       'Method not allowed.',
       Response::HTTP_METHOD_NOT_ALLOWED,
       'method_not_allowed',
   )->withHeaders($exception->getHeaders());
   ```

   Use the actual handler variable name if it is `$e` instead of `$exception`. The important requirement is to call `getHeaders()` and attach those headers to the formatted response.

3. Update the generic `HttpException` handler. It currently formats the body but discards headers. Attach `$exception->getHeaders()` to the formatted response:

   ```php
   return ApiErrorFormatter::response(
       $message,
       $status,
       ApiErrorFormatter::defaultCodeForStatus($status),
   )->withHeaders($exception->getHeaders());
   ```

   Use the existing exception variable name. Do not copy headers from arbitrary `Throwable` objects.

4. Leave the existing `ThrottleRequestsException` handler's header logic in place. It already reads `$e->getHeaders()` and returns `$response->withHeaders($headers)`. Do not remove this behavior. It must continue returning the existing `429` body and `Retry-After` header.

5. Inspect the `RateLimitReachedException` handler. If this exception is returned directly to public API clients and it provides a retry duration, preserve the existing `meta.retry_after_seconds` and also add the standard header:

   ```php
   $retryAfter = $e->getLimit()->getRemainingSeconds();

   return ApiErrorFormatter::response(
       'Too many requests. Please try again later.',
       Response::HTTP_TOO_MANY_REQUESTS,
       'rate_limited',
       meta: ['retry_after_seconds' => $retryAfter],
   )->withHeader('Retry-After', (string) $retryAfter);
   ```

   Only add this if that handler is part of a public API response path. Do not invent a `getHeaders()` call if the exception does not have that method.

6. Keep the following handlers using `ApiErrorFormatter` without adding arbitrary headers: authentication (`401`), authorization (`403`), not found (`404`), validation (`422`), database errors, storage errors, and unexpected production errors. These exceptions normally have no client instruction header.

7. Do not change `ApiErrorFormatter` so that it automatically copies headers. The formatter receives only message/status/code/errors/meta and should remain a body-formatting helper. Header preservation belongs in the handler because only the handler knows whether headers are present and safe to expose.

8. Confirm that empty values remain JSON objects, not arrays. `ApiErrorFormatter` should continue producing:

   ```json
   { "errors": {}, "meta": {} }
   ```

   Do not change this to `[]`.

9. Keep production error messages safe. Do not expose stack traces, SQL, filesystem paths, credentials, or raw provider responses.

### Required tests

Add or extend focused HTTP tests. Do not create a separate test class for every exception.

1. Send an unsupported method to a real API route. Assert:
   - HTTP status is `405`.
   - The `Allow` response header exists and contains the actually supported method.
   - The JSON body has `message`, `code`, `errors`, and `meta`.
   - `code` is `method_not_allowed`.

2. Exercise the normal Laravel throttle path. Assert:
   - HTTP status is `429`.
   - The `Retry-After` response header is preserved.
   - `meta.retry_after_seconds` is present when the source exception provides it.

3. If the `RateLimitReachedException` handler is publicly reachable, test that path too and assert the same `Retry-After` header plus JSON metadata.

4. Assert the raw decoded JSON types for an error with no details:

   ```php
   $this->assertIsObject($response->json('errors'));
   $this->assertIsObject($response->json('meta'));
   ```

   If the test framework returns associative arrays for decoded objects, inspect the raw JSON or use an equivalent assertion that distinguishes `{}` from `[]`.

5. Assert that a production-style `500` response contains the safe public message and does not contain exception text, SQL, stack traces, credentials, or local file paths.

### Do not do these things

- Do not attach arbitrary values from ordinary exceptions as headers.
- Do not change `401`, `403`, `404`, or `422` into different status codes.
- Do not remove `Retry-After` from the existing throttle response.
- Do not hand-build a second error JSON format.
- Do not modify unrelated services, routes, or provider integrations.

### P3.2 completion condition

P3.2 is complete only when the changed handlers preserve safe headers, all public error bodies use the standard formatter, focused tests pass, and the existing exception, authorization, throttling, and production-safe error behavior remains intact.

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
