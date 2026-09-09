# Public API Documentation Synchronization Remediation Plan

## Status

This plan is required before the Daywright public API documentation can be approved for production.

Current readiness assessment: **COMPLETE**.

### Phase Completion Status

- ✅ Phase 0: Restore Repository Integrity and Establish a Baseline
- ✅ Phase 1: Repair the Task Runtime Regression
- ✅ Phase 2: Correct Scramble Bearer Security Configuration
- ✅ Phase 3: Implement Idempotency as One Runtime and Documentation Contract
- ✅ Phase 4: Preserve Custom Error Meaning While Enforcing the Canonical Schema
- ✅ Phase 5: Make the Error Registry the Actual Source of Truth
- ✅ Phase 6: Document Archived-Resource Errors from Real Route Behavior
- ✅ Phase 7: Harden Middleware Response Inference and Global Policy
- ✅ Phase 8: Replace False-Positive Documentation Tests
- ✅ Phase 9: Resolve Scramble Analyzer Warnings
- ✅ Phase 10: Regenerate, Verify, and Publish the Contract

This plan supersedes any completion claim in `exception-hadling-docs.md`. That plan established useful foundations, but final verification found runtime regressions, missing authentication declarations, incomplete idempotency documentation, and operation responses that do not use the canonical error schema.

## Objective

Make the generated OpenAPI document an accurate contract for the public API:

- Every protected operation declares Sanctum bearer authentication.
- Every public operation is explicitly unauthenticated.
- Every documented status, error code, message, metadata shape, request header, and response header can be produced by the backend.
- Every public runtime error has the canonical JSON envelope.
- Operation-specific descriptions and examples are preserved without losing the canonical schema.
- Contract tests fail when authentication, middleware, runtime behavior, or generated schemas drift.
- The complete backend suite passes before `api.json` is published.

## Non-Negotiable Contract

All public API errors must use this envelope:

```json
{
  "message": "Human-readable explanation.",
  "code": "stable_machine_code",
  "errors": {},
  "meta": {}
}
```

OpenAPI must describe the same status, code, field types, optionality, and metadata that the runtime handler emits.

## Phase 0: Restore Repository Integrity and Establish a Baseline ✅

### Issue

The working tree currently reports the tracked `app/` directory as deleted or inaccessible. This prevents reliable implementation, static analysis, and test execution. It also makes unrelated accidental deletions likely to enter the documentation commit.

### Resolution

1. Determine why Git reports the tracked application files as deleted.
2. Restore access to those files from the correct working copy or branch without discarding intentional user changes.
3. Confirm that only intended documentation work remains modified.
4. Record the current Scramble version from `composer.lock` and review all transitive dependency changes caused by the upgrade.
5. Run a baseline test suite before making further changes.

### Verification

```bash
git status --short
composer show dedoc/scramble
composer test
```

### Acceptance Criteria

- The application source tree is present and readable.
- There are no unexplained mass deletions.
- Existing failures are recorded before remediation begins.

## Phase 1: Repair the Task Runtime Regression ✅

### Issue

`TaskService` references `PlanLimitType` and `TaskSystemStatus`, but their imports were removed. Task creation therefore throws a class-not-found exception and returns `500`.

This is a production blocker even though it is not an OpenAPI generation problem. Documentation cannot be declared synchronized with a backend that fails on a documented success path.

### Resolution

1. Restore the required enum imports in `app/Services/Task/TaskService.php`:
   - `App\Enums\Subscription\PlanLimitType`
   - `App\Enums\TaskSystemStatus`
2. Remove unrelated unused imports introduced in the same change.
3. Run focused task create, update, archive, restore, assignment, and authorization tests.
4. Run static analysis to detect other missing imports before continuing.

### Acceptance Criteria

- An authorized user can create a task successfully.
- Task creation no longer returns `500`.
- No missing-class or unused-import errors remain in `TaskService`.

## Phase 2: Correct Scramble Bearer Security Configuration ✅

### Issue

`config/scramble.php` currently uses the unsupported key `security` and the wrong class namespace. Scramble 0.13.42 expects the singular `security_strategy` key and the strategy in `Dedoc\Scramble\SecurityDocumentation`.

Because global security was removed at the same time, the generated specification currently contains no bearer security scheme and protected operations have no security requirement. A documented `401` response does not establish that authentication is required.

### Resolution

1. Replace the invalid configuration with the installed Scramble API:

   ```php
   'security_strategy' =>
       \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
   ```

2. Keep global `$openApi->secure(...)` disabled after middleware-derived security works, to avoid incorrectly securing public operations.
3. Regenerate the specification and inspect:
   - `components.securitySchemes`
   - root-level `security`, if generated
   - operation-level `security`
4. Compare every public API route using `auth:sanctum` against its OpenAPI operation.
5. Verify excluded session, first-party, admin, and webhook routes do not enter the public document.

### Required Contract Tests

- A protected route has a non-empty bearer security requirement and a `401` response.
- `/v1/scopes` has `security: []` and no inherited authentication requirement.
- Every route with `auth:sanctum` is protected in OpenAPI.
- No route without public API authentication is accidentally marked protected.
- Exactly one bearer security scheme is generated.

Do not use `?? []` when asserting required security fields. That converts a missing property into the same value expected for a public route and creates a false positive.

### Acceptance Criteria

- Zero protected operations are missing bearer security.
- Public operations remain explicitly unauthenticated.

## Phase 3: Implement Idempotency as One Runtime and Documentation Contract ✅

### Issue A: Incorrect Attribute Type

`Dedoc\Scramble\Attributes\Header` describes response headers. It does not create an `Idempotency-Key` request parameter. Request headers require `HeaderParameter`.

### Issue B: Incorrect Success Status

Scramble's response `Header` attribute defaults to status `200`. Conversation creation returns `201`, so `Idempotency-Replayed` is not attached to the actual success response.

### Issue C: Generic Package Error Codes

The installed `wendelladriel/laravel-idempotency` package throws generic Symfony `HttpException` instances with standard HTTP statuses. The application handler maps these to generic codes:

- Missing key: `400 bad_request`
- Request in progress: `409 conflict`
- Key reused with different payload: `422 validation_error`
- Cache without atomic locks: `500 internal_server_error`

Stable idempotency-specific codes (like `idempotency_key_required`) are not emitted by the runtime and should not be documented unless the package behavior changes.

### Resolution

1. **Keep runtime codes aligned with current behavior**: Use the existing generic formatter codes and document idempotency meaning in response descriptions and named OpenAPI examples.
2. **Fix the Scramble transformer** to recognize idempotent middleware and add proper contract:
   - Add required `Idempotency-Key` request header using `HeaderParameter`
   - Mark as `string` with realistic example
   - Add `400`, `409`, and `422` responses with canonical envelope
   - Add `Retry-After` to `409` when applicable
   - Add optional `Idempotency-Replayed` to actual success status (e.g., `201`)
3. **Recognize middleware variations**: Support `Idempotent::class`, `Idempotent::using(...)`, `idempotent` alias, and custom header names
4. **Apply only to public routes**: Exclude session, first-party, admin, and webhook routes per existing provider filtering
5. **Remove redundant controller attributes** after middleware extension generates complete contract

### Required Tests

Runtime tests must trigger all idempotency scenarios:

- Missing key → 400 bad_request
- Concurrent request → 409 conflict
- Same key with changed payload → 422 validation_error
- Successful replay with Idempotency-Replayed header
- Missing atomic-lock support → 500 internal_server_error

Generated-document tests must assert:

- `Idempotency-Key` exists in `parameters` with `in: header` and `required: true`
- Parameter schema is `string` with expected example
- `Idempotency-Replayed` exists under actual success status (e.g., `201`)
- Error responses contain canonical envelope schema
- Each error contains correct generic code with idempotency-specific example
- `Retry-After` is documented on `409` when supported

Replace the current weak `$hasIdempotencyKey` computation with strict assertions.

### Acceptance Criteria

- Runtime and OpenAPI accurately document the package's actual generic error codes
- Every idempotent public operation receives the complete header contract automatically
- Idempotency-specific behavior is described in examples, not in separate error codes

## Phase 4: Preserve Custom Error Meaning While Enforcing the Canonical Schema ✅

### Issue

The provider currently preserves a custom error response by skipping shared-response replacement. This keeps the description but can leave the response without any JSON schema.

Known affected responses include:

- Invitation create, accept, and reject `422`
- Project update `400`
- Task assign and unassign `422`

OpenAPI allows only one response object per status, so multiple meanings for `403`, `409`, or `422` must be represented inside that response through a shared schema and named examples.

### Resolution

Refactor response composition into a merge operation instead of an all-or-nothing replacement:

1. Preserve the operation-specific description.
2. Preserve explicitly documented response headers.
3. Preserve existing examples and add named business examples where required.
4. Ensure `content.application/json.schema` references the canonical error-envelope schema.
5. Use complete shared response references only for genuinely generic responses with no operation-specific detail, such as generic `401`, fallback `500`, and middleware-derived `429`.
6. When an existing response is already a component reference, resolve it before deciding whether it has a schema or valuable custom content.
7. Never consider a status documented merely because its response key exists.

### Required Contract Test

Walk every public operation and every `4xx`/`5xx` response. Each must either:

- Resolve to a component response containing an `application/json` schema, or
- Contain an inline `application/json` schema.

Then resolve the schema and verify it provides `message`, `code`, `errors`, and `meta`.

### Acceptance Criteria

- Zero public API error responses lack a JSON schema.
- Custom descriptions and examples remain visible.
- Every error response resolves to the canonical envelope.

## Phase 5: Make the Error Registry the Actual Source of Truth ✅

### Issue

The current registry is an inventory, not yet a true source of truth:

- Runtime messages still exist separately in `ApiErrorFormatter` and exception classes.
- Documentation descriptions and metadata examples remain in `ScrambleServiceProvider`.
- Registry `meta_schema` values are not used to generate schemas.
- Multiple codes with the same status collide on the same status-based component name, so the first registered definition wins.
- `token_rate_limited` is missing from the registry.

There are also concrete mismatches:

- Documentation gives `project_archived` a `resource_type` metadata field, but runtime emits empty metadata.
- Documentation shows `plan_limit_exceeded.upgrade_required` as `false`, while runtime emits `true`.

### Resolution

1. Key registry entries by stable error code, not by HTTP status.
2. Define for every public code:
   - HTTP status
   - Default public message
   - Consumer-facing description
   - Metadata schema
   - Complete example
3. Add all reachable public codes, including `token_rate_limited` and the three idempotency codes.
4. Make `ApiErrorFormatter` consume registry defaults for generic errors.
5. Make public `ApiException` classes use registry codes and defaults while retaining their dynamic metadata.
6. Make Scramble schema/example generation consume the same registry entry.
7. Keep status-level envelope schemas reusable, but put code-specific named examples on the affected operation response.
8. Do not register several responses under the same status-derived component name.
9. Resolve metadata mismatches conservatively:
   - Remove undocumented archived-resource metadata unless runtime intentionally adds it.
   - Set the plan-limit example to the value runtime emits.
10. Add a registry validation test that rejects duplicate codes, invalid statuses, incomplete examples, and examples that do not satisfy their metadata schema.

### Acceptance Criteria

- Every documented code exists in the registry.
- Every registry code reachable from the public API has a runtime test and at least one documented operation or an explicitly recorded global policy.
- No code, message, or metadata example silently depends on registration order.

## Phase 6: Document Archived-Resource Errors from Real Route Behavior ✅

### Issue

Searching the controller action string for `withTrashed` cannot detect Laravel route binding behavior. The action string identifies a controller method, not the route's binding configuration. The current broad test passes when any unrelated route has a `409`, so it does not prove archived-resource coverage.

### Resolution

1. Audit the custom `project` and child `task` binding code and list the exact public operations that can throw:
   - `project_archived`
   - `task_archived`
2. Test restore and force-delete routes separately because their trashed binding behavior may be intentionally different.
3. Choose an explicit, statically reliable documentation mechanism:
   - Prefer a reusable operation-level error attribute or annotation keyed by `ErrorCode`.
   - Alternatively, use verified Laravel route metadata such as `allowsTrashedBindings()` where that exactly matches runtime behavior.
4. Remove `hasWithTrashed()` string inspection.
5. Attach the canonical `409` schema plus named archived-resource examples only to confirmed operations.

### Required Tests

- Runtime binding test for active, missing, and archived projects.
- Runtime binding test for active, missing, and archived child tasks.
- Exact OpenAPI assertion for each affected operation.
- Negative assertion proving unrelated operations do not gain archived-resource `409` responses.

### Acceptance Criteria

- Every operation that can emit an archived-resource code documents it.
- No unrelated operation advertises it.

## Phase 7: Harden Middleware Response Inference and Global Policy ✅

### Issue

Middleware matching is brittle for aliases, resolved class names, and parameterized middleware. The normalization method computes a short class name but can return the original unnormalized value. `Authorize:access,project` therefore may not match `Authorize`.

The provider also still ensures `429` globally, although the agreed policy says `429` belongs only on throttled operations. This can make the specification promise behavior the route does not enforce.

### Resolution

1. Normalize middleware into a consistent structure containing:
   - Resolved or aliased base middleware name
   - Parameter list
   - Original declaration for diagnostics
2. Support and test:
   - `tokenAbility:projects:read`
   - Resolved `CheckTokenAbilities:projects:read`
   - `can:access,project`
   - Resolved `Illuminate\Auth\Middleware\Authorize:access,project`
   - `throttle:sensitive-upload`
   - Idempotency aliases and resolved classes
3. Treat an existing response status as present whether it is inline or a reference. Do not add duplicate responses.
4. Apply ownership consistently:
   - `401`: Scramble security strategy
   - `403`: token ability, policy, and subscription middleware or explicit business behavior
   - `429`: throttle middleware only
   - `500`: global production fallback
   - `400`, `404`, `409`, `422`, `503`: inferred or explicitly attached to confirmed operations
5. Remove global `429` injection after middleware tests pass.
6. Remove unused transformer methods and dead normalization variables.

### Acceptance Criteria

- Generated statuses exactly match actual middleware behavior.
- No operation gets `429` without throttling.
- Aliased and resolved middleware produce identical documentation.

## Phase 8: Replace False-Positive Documentation Tests ✅

### Issue

Several tests currently prove only that a status or non-empty schema fragment exists. They do not prove that the final contract is correct. Exact resource-reference assertions were also weakened to `assertNotEmpty`, allowing wrong resource schemas to pass.

### Resolution

1. Restore exact expected resource schema assertions for subscriptions, invitations, projects, and conversations.
2. Replace status-only assertions with assertions for:
   - Exact status
   - Resolved schema reference or inline schema
   - Exact error code example
   - Description
   - Required headers
   - Header status placement
   - Security requirement
3. Replace the broad "at least one route has 409" test with exact operation assertions.
4. Test both inline responses and component references using shared resolver helpers.
5. Add a route-to-document parity data provider that compares actual public middleware with generated OpenAPI.
6. Add representative runtime/OpenAPI parity tests for:
   - Authentication
   - Authorization
   - Validation
   - Throttling
   - Idempotency
   - Archived resources
   - Plan limits
   - Subscription requirements
7. Keep generated-document tests and runtime tests separate so failures identify whether generation or backend behavior drifted.

### Acceptance Criteria

- Removing a security scheme, header, canonical schema, named example, or expected resource reference makes a test fail.
- Tests do not substitute defaults for missing required OpenAPI properties.

## Phase 9: Resolve Scramble Analyzer Warnings ✅

### Issue

`scramble:analyze` currently reports warnings for model inference, pivot schema inference, and redundant PHPDoc types. An exit code of zero does not mean the generated schemas are accurate.

### Resolution

1. Capture and classify every analyzer warning.
2. For resource model-inference warnings, add the Scramble-supported model context or explicit return/property type needed by the analyzer.
3. Verify computed and aggregate resources independently when no Eloquent model is appropriate.
4. Ensure test migrations expose required pivot-table schema to the analyzer.
5. Remove redundant `@var` annotations where native model casts or return types are already more precise.
6. If a warning is proven harmless and cannot be removed, document a narrowly scoped baseline with its reason. Do not ignore all warnings globally.
7. Compare the affected generated schemas before and after each fix.

### Acceptance Criteria

- `scramble:analyze` is clean, or every remaining warning has a reviewed and version-controlled baseline.
- No public resource schema depends on an unresolved model type.

## Phase 10: Regenerate, Verify, and Publish the Contract ✅

### Resolution

Run verification in this order:

1. Focused runtime tests for every repaired behavior.
2. Documentation contract tests.
3. Exception-handler tests.
4. Complete PHPUnit suite.
5. Static analysis.
6. Formatting checks.
7. Scramble analysis.
8. Regenerate `api.json` only after all preceding checks pass.
9. Review the generated diff for route, security, schema, example, and dependency changes.

Recommended commands:

```bash
php artisan test tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php
php artisan scramble:analyze
composer test
composer stan
composer pint:test
```

Use the repository's established export command to regenerate `api.json`, then assert the committed artifact has no unexplained diff after regeneration.

### CI Release Gate

CI must fail when:

- Tests fail.
- Static analysis or formatting fails.
- OpenAPI generation fails.
- A protected route lacks bearer security.
- Any public error response lacks the canonical schema.
- Runtime and documented error-code parity tests fail.
- Regenerating `api.json` changes the committed artifact.

### Acceptance Criteria

- Full backend suite passes.
- Documentation suite passes with strict assertions.
- Analyzer warnings are resolved or explicitly baselined.
- Generated `api.json` is reproducible.
- Dependency changes are reviewed.

## Implementation Order

Implement phases in this sequence because later work depends on earlier contracts:

1. Phase 0: repository integrity
2. Phase 1: backend regression
3. Phase 2: authentication
4. Phase 3: idempotency runtime and docs
5. Phase 5: registry synchronization
6. Phase 4: canonical response composition
7. Phase 6: archived-resource mapping
8. Phase 7: middleware inference and global policy
9. Phase 8: strict contract tests
10. Phase 9: analyzer cleanup
11. Phase 10: final generation and CI gate

Phases 4 and 5 should be developed together in one branch or consecutive commits because response composition depends on the final registry shape.

## Suggested Commit Boundaries

1. `fix: restore task service runtime imports`
2. `fix: configure middleware-derived scramble security`
3. `feat: stabilize public idempotency error contract`
4. `refactor: centralize runtime and openapi error definitions`
5. `fix: preserve operation error details with canonical schemas`
6. `fix: document archived binding errors explicitly`
7. `test: enforce runtime and openapi contract parity`
8. `docs: resolve scramble analysis warnings and regenerate spec`
9. `ci: verify generated public api contract`

Do not combine the TaskService runtime repair with the documentation refactor. Keeping it isolated makes the production regression easy to review and backport.

## Final Definition of Done

The documentation is production-ready only when all conditions below are true:

- [x] The repository contains no unexplained deleted application files.
- [x] All backend tests pass, including task creation.
- [x] All protected operations declare bearer security.
- [x] All public operations remain unauthenticated.
- [x] Idempotency request and response headers are generated correctly with generic error codes.
- [x] Runtime emits documented idempotency behavior via generic codes and examples.
- [ ] Every public `4xx` and `5xx` response resolves to the canonical envelope.
- [ ] Operation-specific descriptions and examples are preserved.
- [ ] Business-code metadata examples match runtime values.
- [ ] Archived-resource responses are attached to exact affected operations.
- [ ] `429` appears only where throttling can occur.
- [ ] Exact resource schema references are protected by tests.
- [ ] Scramble analyzer warnings are resolved or reviewed and baselined.
- [ ] `api.json` regenerates reproducibly with no unexplained diff.
- [ ] Composer dependency changes are reviewed.
- [ ] CI enforces the OpenAPI contract before deployment.

Until every checkbox is complete, the correct readiness verdict remains \*
