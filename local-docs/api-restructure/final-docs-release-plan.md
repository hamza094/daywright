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

## Remaining Work

### Phase 3: Finish the Idempotency Contract - Complete

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

Verification: **55 focused documentation/runtime tests pass, PHPStan passes, and Pint passes**.

### Phase 4: Resolve Middleware Before Adding Errors - Complete

#### Issue

Raw route middleware may contain group names instead of expanded throttle or authorization middleware. Documentation must inspect Laravel's resolved middleware list.

#### Implementation

1. Resolve route middleware through Laravel's `Router::gatherRouteMiddleware()` API so groups, aliases, exclusions, and ordering match runtime behavior.
2. Continue using the existing normalized parser for aliases, class names, and parameters, including resolved `ThrottleRequests` classes.
3. Apply only deterministic middleware responses:
   - authentication: `401`
   - authorization and token abilities: `403`
   - throttling: `429`
   - idempotency: `400`, `409`, and `422`
4. Keep `429` off routes without resolved throttle middleware.
5. Verify `GET /v1/scopes` remains unauthenticated and receives its runtime `429` contract.

#### Acceptance Criteria

- Every runtime-throttled public route documents `429` and `Retry-After`.
- Routes without throttling do not receive a middleware-derived `429`.
- Aliased and resolved middleware produce the same result.

Verification: **38 focused tests pass, PHPStan passes, and Pint passes**.

### Phase 5: Explicit Business Errors and HTTP Method Parity - Complete

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

#### Implemented

- Added repeatable `#[ApiError(ErrorCode::...)]` controller attributes with registry validation.
- Applied business-error metadata in the provider's final OpenAPI pass so later Scramble extensions cannot overwrite it.
- Preserved canonical error envelopes, realistic examples, and multiple business codes sharing one HTTP status.
- Cloned and deduplicated same-status responses so operation-specific metadata cannot leak between operations.
- Documented both `PUT` and `PATCH` for public combined resource update routes with distinct operation IDs.

Verification: **56 focused documentation/runtime tests pass (4,586 assertions), PHPStan passes, and Pint passes**.

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

#### Implemented

- Replaced the hard-coded `PUT`/`PATCH` OpenAPI duplication list with runtime route-method detection. Every released Laravel route that accepts both verbs now documents both verbs with distinct operation IDs; this fixed the missing `PATCH /v1/users/{user}` operation.
- Added a route-resolver-backed OpenAPI parity suite. It verifies every released runtime method appears in the document, every documented operation resolves back to a released route, and authentication, authorization, throttling, and idempotency middleware contracts are reflected in OpenAPI.
- Added the missing archived-binding runtime regression: an archived project is accepted only by a `withTrashed()` route and returns `409 project_archived` on a normal bound route.
- Corrected the archived-route documentation test so multiple verbs on the same path are all asserted rather than overwritten by duplicate array keys.

#### Verification

- `RuntimeOpenApiParityTest`: passes with 596 assertions.
- `ArchivedResourceBindingTest`: passes with 11 tests, including the new non-`withTrashed()` case.
- Full PHPUnit suite: 1,007 tests and 9,373 assertions pass in 4m43s.
- `composer stan`: passes.
- `composer pint:test`: passes.
- Fresh temporary OpenAPI export confirms `GET`, `PUT`, `PATCH`, and `DELETE` on `/v1/users/{user}`.
- `scramble:analyze` reports five existing, non-contract-impacting inference warnings for intentionally non-model computed resources and Laravel's abstract `Pivot` model.

#### Resolved Release Gates

- Composer's child-process timeout is now 1,800 seconds, which accommodates the full suite while retaining a finite CI failure bound.
- Pint normalized the existing repository formatting baseline; the same `composer pint:test` command used by CI now passes.

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

#### Implemented

- Regenerated tracked `api.json` from Scramble after all Phase 6 checks passed.
- Compared the tracked export with an independently generated temporary export using an object-key-order-insensitive JSON comparison.
- Retained deliberate public documentation access through the configured `web` middleware. Public API route selection continues to exclude session-authenticated, first-party-authenticated, administrative, webhook, browser-authentication, and unreleased endpoints.

#### Verification

- `api.json` and the independent fresh export are semantically equal.
- The released contract contains 41 paths, 10 tags, version `0.3.1`, and server URL `/api`.
- The generated document includes no excluded public-route prefixes covered by the documentation contract tests.
- `composer test`, `composer stan`, and `composer pint:test` pass.
- `scramble:analyze` retains only the five known non-contract-impacting warnings described in Phase 6.

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

1. Finish automatic idempotency documentation.
2. Resolve middleware groups and verify throttling.
3. Add explicit confirmed business errors.
4. Resolve `PUT|PATCH` method parity.
5. Run full tests, PHPStan, Pint, and Scramble analysis.
6. Export and semantically verify `api.json`.

## Final Definition of Done

- [x] Canonical error envelopes are enforced.
- [x] Archived-resource documentation matches Laravel binding behavior.
- [x] Idempotent public routes generate their complete contract automatically.
- [x] Resolved throttle middleware and documented `429` responses match.
- [x] Confirmed business errors are explicitly documented.
- [x] Public runtime methods and OpenAPI methods match.
- [ ] Full PHPUnit suite passes.
- [ ] PHPStan passes.
- [ ] CI's Pint gate passes.
- [ ] Scramble analysis has no new contract-impacting warnings.
- [ ] `api.json` is regenerated and semantically reproducible.
- [ ] Production documentation access and metadata are verified.

The documentation is production-ready only when every unchecked item above is complete.
