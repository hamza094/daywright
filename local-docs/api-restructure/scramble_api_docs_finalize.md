local-docs/api-restructure/final-docs-release-plan.md
local-docs/api-restructure/final-docs-release-plan.md

# Simplified Public API Documentation Release Plan

## Status

The public API documentation is not ready for release until the remaining correctness gates in this plan pass.

This plan replaces the earlier broad remediation plan. It targets contract correctness and maintainability instead of complete automatic inference or exhaustive documentation polish.

Estimated remaining effort: **5-9 focused engineering hours**.

## Scope

This release covers only routes included by `ScrambleServiceProvider` as public third-party API operations. Session-authenticated, first-party, admin, webhook, and explicitly excluded routes remain outside this contract.

## Production Contract

All documented public errors use one of two reusable schemas:

1. `PublicApiErrorEnvelope` for non-validation errors.
2. `PublicApiValidationErrorEnvelope` for validation errors.

Both schemas must serialize this stable shape:

```json
{
  "message": "Human-readable explanation.",
  "code": "stable_machine_code",
  "errors": {},
  "meta": {}
}
```

Scramble remains responsible for request validation schemas, resource responses, controller return types, and standard framework exceptions. Custom code handles only behavior that Scramble cannot infer reliably.

## Completed Work

### Phase 1: Canonical Error Envelopes - Complete

- Referenced and inline public error responses are normalized to the canonical schema.
- Operation descriptions and response headers are preserved.
- The complete Scramble contract suite passes 24 tests and 4,213 assertions.
- Canonical-envelope checks account for 3,471 assertions.

### Phase 2: Archived Route Binding - Complete

- Archived errors use Laravel's actual `Route::allowsTrashedBindings()` state.
- Route parameters come from `$route->parameterNames()`.
- Route names, URI regular expressions, and controller action strings are not used for inference.
- Routes using `withTrashed()` do not advertise archived-binding failures.
- Existing `409` responses are merged so idempotency's `Retry-After` header is retained.
- Nested task updates document both `project_archived` and `task_archived`.
- Archived OpenAPI checks pass 24 assertions.
- Archived runtime binding tests pass 10 tests.
- Focused PHPStan and Pint checks pass.

### Phase 4: Resolve Middleware Before Adding Errors - Complete

- Implemented middleware group resolution through Laravel's router
- Added `resolveRouteMiddleware()` to look up routes by name and get their middleware
- Added `expandMiddlewareGroups()` to recursively expand middleware groups (e.g., 'api' → 'throttle:api')
- Removed global 429 injection from `ScrambleServiceProvider`
- Re-added middleware-specific 429 detection using resolved middleware
- Updated tests to verify 429 on throttled routes including inherited throttling
- Verified `GET /v1/scopes` gets 429 response (inherits `throttle:api` from API middleware group)
- All 24 documentation tests passing (4,245 assertions)

## Remaining Work

### Phase 3: Finish the Idempotency Contract

#### Issue

Idempotent operations must expose the same request and response contract without depending on repeated controller annotations.

#### Implementation

1. Detect resolved `Idempotent` middleware in the existing operation transformer.
2. Add exactly one `Idempotency-Key` request header parameter.
3. Derive its name and required state from middleware or package configuration.
4. Document the runtime statuses and generic codes currently emitted by the package boundary:
   - `400 bad_request` for a missing required key
   - `409 conflict` while a matching request is being processed
   - `422 validation_error` when a key is reused with different data
5. Keep `Retry-After` on the idempotency `409`.
6. Add `Idempotency-Replayed` to the actual successful response.
7. Remove duplicate controller annotations only after generated output is verified.

#### Acceptance Criteria

- Every public route with idempotency middleware has the complete contract automatically.
- No non-idempotent route receives idempotency headers or errors.
- Runtime and OpenAPI use the same statuses and generic machine codes.

Estimated effort: **1-1.5 hours**.

### Phase 5: Explicit Business Errors and HTTP Method Parity

#### Business Error Policy

Do not attempt to statically discover exceptions thrown deep inside controllers, actions, or services.

Use one repeatable operation-level attribute for uncommon business errors:

```php
#[ApiError(ErrorCode::PLAN_LIMIT_EXCEEDED)]
#[ApiError(ErrorCode::TASK_NOT_TRASHED)]
```

The attribute reads status, description, and one realistic example from `ErrorCode`. Continue using the two shared envelope schemas; do not create a unique schema or global `oneOf` list for every code.

Only attach an error after confirming that the exact operation can emit it. Retain `ArchivedResourceErrorResponse` only for controller or service behavior that rejects archived state independently of route binding.

#### HTTP Method Parity

Laravel resource update routes currently accept `PUT|PATCH`, while Scramble publishes only `PUT` for the combined route.

Choose one intentional public contract:

1. Before public release, prefer registering only `PATCH` for partial updates; or
2. If both methods are already supported publicly, register and document `PUT` and `PATCH` explicitly with distinct operation IDs.

Apply the decision consistently to all public resource update routes.

#### Acceptance Criteria

- Confirmed business errors appear only on reachable operations.
- Status, code, description, and metadata agree with runtime.
- Every runtime public HTTP method has a corresponding OpenAPI operation.

Estimated effort: **1.5-2.5 hours**.

### Phase 6: Final Contract and Quality Gates

#### Required Tests

1. Every protected public route has bearer security.
2. Every public unauthenticated route explicitly has no security requirement.
3. Every `4xx` and `5xx` response resolves to a canonical envelope.
4. Every idempotent route has its request header, response header, and error statuses.
5. Every resolved throttle middleware has `429` and `Retry-After`.
6. Archived binding behavior matches `withTrashed()` exactly.
7. Registered public HTTP methods match generated path methods.
8. Representative runtime tests confirm authentication, validation, idempotency, throttling, archived resources, and explicitly documented business errors.

#### Required Commands

```bash
php artisan test tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php
php artisan test tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php
php artisan test tests/Feature/Api/V1/Projects/ArchivedResourceBindingTest.php
composer test
composer stan
composer pint:test
php artisan scramble:analyze
```

Existing PHPUnit doc-comment metadata warnings should be migrated to PHPUnit attributes when those tests are edited. They do not block runtime correctness, but this work must not add new deprecations.

#### Acceptance Criteria

- Full PHPUnit suite passes.
- PHPStan passes.
- The same Pint command used by CI passes.
- Scramble analysis has no new contract-impacting warning.

Estimated effort: **1.5-2 hours**.

### Phase 7: Export and Release

1. Run `php artisan scramble:export` only after Phases 3-6 pass.
2. Commit generated `api.json` without manual edits.
3. In CI, export to a temporary file and compare parsed JSON structures with the committed export.
4. Do not require byte-for-byte equality when formatting is the only difference.
5. Review the server URL, title, version, public route filtering, documentation access policy, and bearer security.

#### Acceptance Criteria

- A fresh export is semantically equal to committed `api.json`.
- Production documentation access is intentionally public or authorization-gated.
- No excluded route appears in the public specification.

Estimated effort: **0.5-1 hour**.

## Deferred Polish

These items are explicitly not release blockers:

- Named media-type examples for every error condition.
- A unique OpenAPI schema for every business error code.
- Automatic discovery of exceptions thrown inside services.
- Global `oneOf` catalogs containing unreachable errors.
- Automated enforcement of the Scramble warning baseline.
- Byte-for-byte JSON export comparison.
- Broad refactoring of `ErrorCode` beyond confirmed runtime mismatches.
- Repository-wide PHPUnit metadata cleanup unrelated to changed tests.

Deferred work must not be described as already implemented.

## Implementation Order

1. ~~Resolve middleware groups and verify throttling~~ ✅ Complete
2. Finish automatic idempotency documentation.
3. Add explicit confirmed business errors.
4. Resolve `PUT|PATCH` method parity.
