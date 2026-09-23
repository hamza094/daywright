# Phase 3: API contract stabilization

Finish the public API behavior before release. Use the [source audit](../rest-production-audit.md) for the original reproductions, but check today's routes and code first. Preserve existing authorization and first-party boundaries.

## Release work

### P3.1: updates and no-op requests

Inspect `routes/api/v1.php`, project/task route files, update requests, services, and `HasStateMachine`. The existing partial-update behavior should have one clear HTTP method contract. Prefer PATCH for partial updates; retain PUT only if it has a real full-replacement contract or an existing client needs a documented transition. Remove validation that rejects an unchanged project name/about/notes. A setter to an already-held state may succeed when the state permits it, without repeating notifications or other side effects. Keep terminal transition rules.

Test one partial update, one no-op, and one forbidden terminal transition through HTTP. Synchronize the method documentation.

### P3.2: error responses

In `HandlesApiExceptions`, preserve exception headers such as `Allow` and `Retry-After`. Keep the existing 429 behavior. Use `ApiErrorFormatter` for authentication boundary responses, and emit empty `errors`/`meta` as JSON objects, not arrays.

Test a real 405 and its `Allow` header, one retry header, raw empty-object JSON types, and a safe 5xx body. Extend existing tests instead of creating a new suite for every middleware.

### P3.3: pagination links

For project, meeting, and admin-user listings, append only validated canonical filters, sort, and page size to paginator links. Do not carry the current `page` value into the next link. Follow one generated next link in a feature test and confirm the same authorized filtered collection. Fix other listings only if inspection finds the same bug.

### P3.4: subscription routes

The current `routes/api/v1/users.php` already registers a subscription singleton. Verify `route:list` has only the supported API operations and no form `create`/`edit` routes. Keep the existing auth, idempotency, and throttle middleware. No route refactor is needed if this check passes.

### P3.5: infrastructure errors

Inspect `FileService` and `UserService`. Invalid input remains a validation response; storage/network failures must render as safe 5xx errors and be reported without secrets. Add one focused failure-path test for each affected service only if the existing tests do not prove this boundary.

### P3.6: publish the actual contract

Decide whether any changed v1 route already has consumers before removing a method or changing request/response meaning. Document the chosen compatibility path, including any task/project version precondition from P2.5 and pending/unknown Zoom or Paddle responses. Update source annotations/transformers, regenerate `api.json` with `php artisan scramble:export`, and compare the export to runtime routes and responses. Run the existing docs/parity tests. Do not hand-edit generated JSON.

## Deferred

P3.7 resource renaming, bulk-job naming, and two-factor status naming can follow release. Keep existing names stable until a versioned compatibility change is planned.

## Acceptance

Run focused HTTP tests for the changed behavior, the docs/parity tests, `composer test`, and `composer stan`. Verify runtime routes, error headers/types, next-page links, and the fresh OpenAPI export. Record any intentional v1 compatibility exception. Phase 4 handles real process and performance evidence.
