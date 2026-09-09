# Public API Error Documentation Production Plan

## Objective

Make the generated OpenAPI specification accurately reflect Daywright’s runtime authentication, middleware, validation, business exceptions, error codes, metadata, and status codes without claiming every possible error is globally injected.

## Phase 1: Establish the Error Contract and Global Policy

Document the canonical runtime envelope as the non-negotiable contract:

```json
{
  "message": "Human-readable explanation.",
  "code": "stable_machine_code",
  "errors": {},
  "meta": {}
}
```

Tasks:

- **Build a runtime-to-documentation matrix** from existing sources before creating a new catalog:
  - `ApiErrorFormatter` maps standard HTTP statuses to default codes
  - `HandlesApiExceptions` trait maps Laravel, infrastructure, and custom exceptions into the envelope
  - Individual `ApiException` subclasses define business codes (project_archived, plan_limit_exceeded, subscription_required)
  - `ScrambleServiceProvider` separately duplicates status, message, code, and example definitions
- Keep `ApiErrorFormatter` as the only public API envelope formatter.
- Confirm every exception handler and middleware failure uses this envelope.
- **Define the global error policy** (what errors are truly global vs operation-specific):
  - `500`: valid as the production fallback (globally ensured).
  - `401`: add only to authenticated routes.
  - `403`: add only from scope, policy, or subscription middleware.
  - `404`: rely on model-binding inference or explicit route behavior.
  - `405`: keep as a shared runtime component; do not attach to every operation.
  - `503`: attach only where an external dependency can affect the operation.
  - `400`, `409`, `422`: infer or explicitly attach based on actual behavior.
  - `429`: add only from `throttle:*` middleware (not globally).
- After understanding duplication and ownership, create an **executable or generated** error-code catalog:
  - The handler and Scramble provider should consume the same PHP registry
  - Or generate a human-readable catalog from that registry
  - Avoid creating another manually maintained Markdown catalog
- **Treat the error-code list as an initial inventory**. The reachability audit should determine the complete set of codes public consumers can receive, including:
  - `bad_request`
  - `unauthenticated`
  - `forbidden`
  - `not_found`
  - `method_not_allowed`
  - `conflict`
  - `validation_error`
  - `rate_limited`
  - `internal_server_error`
  - `service_unavailable`
  - `project_archived`
  - `task_archived`
  - `plan_limit_exceeded`
  - `subscription_required`
  - `invalid_state_transition`
  - `storage_error`
  - `database_error`
  - `token_rate_limited`
- Document each code's status, meaning, and possible `meta` properties.
- **Idempotency errors: use stable codes** (not generic validation_error):
  - `idempotency_key_required`
  - `idempotency_in_progress`
  - `idempotency_key_reused`
- Do not map vendor errors by parsing exception messages. Introduce an application-owned exception or middleware boundary that translates vendor failures into stable Daywright errors.
- Note: Changing existing public error codes is a contract change. Include in release notes if consumers already depend on generic values.

Acceptance criterion: every documented code is emitted by the backend exactly as documented.

## Phase 2: Upgrade Scramble

The project permits `^0.13.2` but currently locks `v0.13.22`.

**Minimum target: `0.13.24`** - This release introduced `MiddlewareAuthSecurityStrategy`, which provides:

- Authentication derived from route middleware
- Bearer security on protected operations
- `security: []` on public operations
- Automatic `401` responses for protected operations
- Fixes the current `/v1/scopes` security mismatch

Tasks:

- Upgrade to the latest compatible `0.13.x` release, at minimum `0.13.24`.
- Review Scramble release notes for inference or schema changes.
- Run the complete test suite after updating `composer.lock`.
- **Review every transitive dependency change** before committing.
- Regenerate and compare OpenAPI before and after the upgrade.

## Phase 3: Fix Security Documentation

Tasks:

- Configure `MiddlewareAuthSecurityStrategy` in `config/scramble.php`.
- Remove the global `$openApi->secure(...)` call once middleware security is enabled.
- Verify protected `auth:sanctum` operations inherit bearer security and contain `401`.
- Verify public operations explicitly contain `security: []`.
- Specifically confirm `GET /v1/scopes` is public in OpenAPI.
- Keep route filtering independent from authentication documentation.

Acceptance criteria:

- Every protected operation requires bearer authentication.
- Every protected operation documents `401`.
- `/v1/scopes` does not require authentication.
- No duplicate security schemes are generated.

## Phase 4: Add Middleware-Aware Error Documentation

Create a dedicated operation transformer, for example:

`app/OpenApi/Transformers/PublicApiMiddlewareResponses.php`

It should inspect `RouteInfo` and add responses based on resolved middleware:

| Runtime middleware    | OpenAPI contract                         |
| --------------------- | ---------------------------------------- |
| `CheckTokenAbilities` | `403`                                    |
| `can:*`               | `403`                                    |
| `throttle:*`          | `429` with `Retry-After`                 |
| `Idempotent`          | Required header plus `400`, `409`, `422` |
| `CheckSubscription`   | `403 subscription_required`              |

**Note:** `auth:sanctum` authentication and `401` responses are handled by Scramble's `MiddlewareAuthSecurityStrategy` (Phase 3). Do not duplicate ownership in this transformer.

**Make middleware matching implementation-safe.** The transformer must recognize aliases, parameterized middleware, and resolved class names, including:

- `tokenAbility:projects:read`
- `CheckTokenAbilities:projects:read`
- `can:access,project`
- `Illuminate\Auth\Middleware\Authorize:access,project`
- Parameterized idempotency middleware

The idempotency rule should automatically add:

- Required `Idempotency-Key` string header
- `400` for a missing header
- `409` while the key is being processed (with `Retry-After` header)
- `422` when reused with different request data
- Optional `Idempotency-Replayed: true` success-response header when the request is a replay of a previously successful request

This will fix `POST /v1/projects/{project}/conversations` and prevent future idempotent routes from being forgotten.

After transformer coverage is tested, remove redundant controller-level idempotency response attributes.

## Phase 5: Document Custom Business Exceptions

**First, perform a public-reachability audit.** The codebase contains more exceptions than the initial list:

- `ArchivedResourceException`
- `InvalidStateTransitionException`
- `TaskNotTrashedException`
- `PlanLimitExceededException`
- `SubscriptionRequiredException`
- `DashboardServiceException`
- Zoom and Paddle exception families
- `ExternalServiceUnavailableException`
- S3 and database exceptions

**Not all belong in the public specification.** Admin, session-only, webhook, and excluded integration paths should not influence public documentation.

Tasks:

- Build a matrix containing:
  - Exception class
  - Status
  - Code
  - Metadata
  - Throwing method
  - Reachable public operations
  - Documentation strategy
- Implement Scramble `ExceptionToResponseExtension` support for public-facing exceptions:
  - `ArchivedResourceException` → `409`
  - `PlanLimitExceededException` → `403`
  - `SubscriptionRequiredException` → `403`
  - `InvalidStateTransitionException` → `422`
  - `TaskNotTrashedException` → `403`
  - **Do not map every `ExternalServiceUnavailableException` to `503`** - it accepts a dynamic status and defaults to `500`. Map concrete subclasses or confirmed throw sites with fixed `503` behavior. Otherwise the spec may promise `503` while runtime emits `500`.
- **Note:** ExceptionToResponseExtension was not implemented due to Scramble API limitations (the `shouldHandle` method receives incorrect type). Route-binding exceptions are handled via the middleware transformer instead.
- Add accurate `@throws` declarations to service methods that expose these exceptions to controllers.
- **Note:** Scramble only reads `@throws` annotations from controller methods, not service methods. Service-level `@throws` declarations are for developer documentation but do not automatically generate OpenAPI responses. To document exceptions in OpenAPI, either:
  - Add `@throws` at the controller level for methods that call services
  - Use the ErrorCode registry and ScrambleServiceProvider mappings (already implemented)
  - Use middleware transformers for route-level behavior

**Route-binding exceptions need special handling** because Scramble may not trace custom model binding:

- Custom archived-resource bindings exist: `Project.php` throws `ArchivedResourceException::project()` when a soft-deleted project is resolved
- Child task binding can throw `ArchivedResourceException::task()`
- Both produce `409`
- Add confirmed archived-resource `409` responses through an operation transformer or explicit operation attributes
- **Before documenting them broadly**, test restore and force-delete routes separately because their binding behavior may intentionally differ

Scramble's supported exception behavior is described in its [response documentation](https://scramble.dedoc.co/usage/response).

## Phase 6: Preserve Operation-Specific Meaning

`replaceOperationErrorResponsesWithSharedReferences()` already exists in `ScrambleServiceProvider`. Its current behavior:

1. Inspects every generated response
2. Recognizes statuses such as `400`, `403`, `409`, and `422`
3. Replaces the complete response with a shared component reference

This standardizes the envelope, but also discards:

- Controller attribute descriptions
- Inferred abort messages
- Business-specific examples
- Business-code context
- Idempotency explanations

Refactor it to:

- Reuse shared schemas for the JSON envelope
- Preserve each operation's response description
- Reference the shared schema from the response content
- Provide operation-specific examples for business errors
- Keep fully shared response components only for genuinely generic responses such as `401`, `429`, and `500`
- **Preserve explicit `404` information too** - some `404`s have useful operation-specific messages (e.g., missing avatar). Use a complete shared response only when the response has no valuable explicit description or example.

For statuses with multiple meanings, especially `403`, `409`, and `422`, provide named examples:

- `plan_limit_exceeded`
- `subscription_required`
- `project_archived`
- `idempotency_key_reused`
- `invalid_state_transition`

Do not describe every `422` as a validation error.

## Phase 7: Update Backend Guidelines

Update the backend guideline with the global error policy defined in Phase 1.

## Phase 8: Add OpenAPI Contract Tests

**Use both test types, with generated-document integration tests as the primary protection.**

Generated OpenAPI integration tests should:

- Boot Laravel normally
- Request `/docs/api.json`
- Inspect the resulting document
- Compare documented routes against actual route middleware
- Assert response references, security, headers, schemas, and examples

Runtime integration tests should separately trigger representative errors and verify actual status, envelope, code, and metadata.

Then add parity assertions connecting both sides. For example:

- The runtime archived-project test proves `409 project_archived`
- The documentation test proves the affected operation advertises that response

Small unit tests are appropriate for response factories and custom Scramble extensions, but unit tests alone cannot validate provider ordering, route middleware resolution, or final document transformations.

Required assertions:

- `auth:sanctum` route → bearer security and `401`
- Public route → `security: []`
- `CheckTokenAbilities` route → `403`
- `can:*` route → `403`
- `throttle:*` route → `429`
- `Idempotent` route → required header and `400`, `409`, `422`
- Form Request route → `422`
- **Confirmed implicit model binding route** → `404` (do not assume every route containing `{parameter}` implies `404`)
- Known archived-resource route → `409`
- **Every documented public operation** → `500`
- **Every documented JSON `4xx` and `5xx` response uses the canonical error envelope schema** (successful responses use their own schemas)
- Explicit response descriptions survive provider normalization
- **Idempotency contract**: `Retry-After` header on `409`, `Idempotency-Replayed: true` on successful replay

Add targeted assertions for stable business codes and `meta` examples.

**Tests should be added alongside each phase, not deferred until Phase 8.**

## Phase 9: CI and Release Controls

Add the following CI checks:

1. Run exception-handler and API contract tests.
2. Run `php artisan scramble:analyze`.
3. **Make OpenAPI export deterministic in CI** - Pin values such as `APP_URL`, `API_VERSION`, environment, and server configuration before export. Otherwise `api.json` can change between developer machines and CI without a contract change.
4. Export the OpenAPI document.
5. Lint it with Redocly CLI or Spectral.
6. Fail when the generated document differs from the committed `api.json`.
7. Run an OpenAPI breaking-change comparison against the main branch.
8. Require explicit approval for removed paths, fields, enum values, or successful responses.

## Final Acceptance Criteria

The documentation is production-ready when:

- Runtime and OpenAPI use the same envelope.
- Middleware-implied failures are documented automatically.
- Custom business exceptions have accurate statuses, codes, and examples.
- Public and protected route security is correct.
- No explicit response description is lost during normalization.
- Generated specification tests pass.
- Scramble analysis passes.
- OpenAPI linting and breaking-change checks pass.
- No error status is documented on an operation unless runtime behavior can produce it.

## Recommended Implementation Sequence

**Phase 1 contract and global-policy decisions are prerequisites. Phase 7 records the finalized policy in the backend guidelines.**

1. **Baseline**: Export the current spec and preserve test results.
2. **Contract decisions**: Complete the error matrix and global-error policy.
3. **Scramble upgrade**: Upgrade and resolve generation differences.
4. **Security**: Enable middleware-derived auth and fix `/v1/scopes`.
5. **Middleware transformer**: Handle token abilities, policies, subscriptions, throttling, and idempotency.
6. **Response normalization**: Preserve explicit descriptions and examples.
7. **Custom exceptions**: Add exception extensions and confirmed operation mappings.
8. **Documentation cleanup**: Remove redundant controller annotations.
9. **CI**: Add linting, parity tests, export checks, and breaking-change detection.

**Tests should be added alongside each phase, not deferred until Phase 8.**

## Incremental Priorities

### P0: Contract Correctness

- Fix `/v1/scopes` security.
- Add missing token-ability `403` responses.
- Document conversation idempotency.
- Add middleware/spec parity tests.

### P1: Business Error Quality

- Document archived-resource `409`.
- Preserve explicit descriptions.
- Add business-code and metadata examples.
- Introduce stable idempotency codes.

### P2: Long-term Controls

- Complete custom exception extensions.
- Add OpenAPI linting.
- Add breaking-change detection.
- Remove redundant annotations and provider duplication.

**Readiness milestones:**

- After P0: security and middleware contract accuracy
- After P1: consumer-facing error semantics are accurate
- After P2: production governance and drift prevention are complete
