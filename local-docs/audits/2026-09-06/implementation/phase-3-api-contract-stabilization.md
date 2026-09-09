# Phase 3: API contract stabilization

Release boundary: **before stabilizing public API**.

Source: `local-docs/audits/2026-09-06/rest-production-audit.md`, priorities 8-10, additional response/route gaps, versioning guidance, and release-roadmap step 3. Findings and reproductions are separated into [findings-reference.md](findings-reference.md).

## SWE 1.6 handoff

Implement this phase in the current Laravel repository, preserving unrelated changes. Confirm referenced symbols because audit line positions can shift. Audit requirements are labeled below. Test cases, proposed file placement, and verification procedures are implementation elaborations.

Prerequisites: phase 1 account/privacy boundaries and phase 2 state/version/integration outcome decisions. Keep those behaviors intact while stabilizing their API contract. Use isolated tests with auth, policies, binding, and exception middleware enabled.

The source audit's forensic probes assert defective behavior. Port them to permanent tests with the intended response and database assertions. This phase focuses on backend/API consumers; no frontend implementation is required.

## P3.1 — Define PUT/PATCH behavior and accept legitimate no-op setters

**Files to modify/review**

- `routes/api/v1.php:26`.
- `routes/api/v1/projects/tasks.php:15`.
- `app/Http/Requests/Api/V1/Project/ProjectUpdateRequest.php:44`.
- `app/Http/Requests/Api/V1/Task/TaskUpdateRequest.php:51`.
- `app/Traits/HasStateMachine.php:31`.
- Supporting routes: `routes/api/v1/projects/core.php`, `routes/api/v1/tasks.php`, `routes/api/v1/users.php`.
- Supporting services: `app/Services/Project/ProjectService.php` and `app/Services/Task/TaskService.php`.

**Implementation checklist — audit requirement**

- [ ] Choose PATCH-only for the existing partial-update contract, or implement separate full replacement semantics for PUT with all required writable fields.
- [ ] Inspect all affected `apiResource` registrations and their request validators; avoid changing only one route while leaving equivalent endpoints ambiguous.
- [ ] Remove project name/about/notes validation that requires submitted values to differ.
- [ ] Accept already-satisfied states for setter-style updates.
- [ ] Send business-change notifications only when relevant attributes actually change.
- [ ] Update documentation and tests to match the chosen behavior.

**Implementation elaboration**

- [ ] Separate resource update semantics from command-style transitions that can legitimately reject repeats.
- [ ] Define replacement scope, required/nullable fields, omission behavior, and protected fields if retaining PUT.
- [ ] If removing PUT from a consumed v1 endpoint, apply the compatibility/deprecation decision in P3.6.
- [ ] Ensure no-op requests do not create unintended duplicate business side effects; preserve the intentional audit policy for recording attempts versus changes.

**Tests to add/update**

- `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php` — replace the existing unchanged-value rejection expectation.
- `tests/Feature/Api/V1/Tasks/TaskTest.php`.
- `tests/Unit/Models/TaskStateMachineTest.php` and `tests/Unit/Models/ProjectStateMachineTest.php`.
- `tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php`.
- [ ] Port the PUT/no-op probe at line 100.
- [ ] PATCH updates selected fields and preserves omitted fields.
- [ ] For PATCH-only routes, PUT receives the selected compatibility response or 405 with correct `Allow` after removal.
- [ ] For supported PUT, missing required replacement fields are rejected and writable omission semantics match the documented replacement contract.
- [ ] Repeating a setter succeeds without duplicate notifications or invalid state changes.
- [ ] Unchanged setters do not enable forbidden transitions from terminal states.

**Verification**

- [ ] Compare runtime method registrations, validators, API documentation, and client-visible responses.
- [ ] Judge idempotency by intended effects; different retry status codes alone do not prove an idempotency violation.

## P3.2 — Preserve HTTP headers and standardize error JSON types

**Files**

- `app/Exceptions/Traits/HandlesApiExceptions.php:70` and `:92`.
- `app/Http/Middleware/RequireSessionAuth.php:45`.
- `app/Http/Middleware/RequireFirstPartyAuth.php:44`.
- `app/Exceptions/Support/ApiErrorFormatter.php:24`.
- Supporting documentation: `app/Documentation/Transformers/PublicApiMiddlewareResponses.php`.

**Implementation checklist — audit requirement**

- [ ] Copy exception headers in both method-not-allowed and generic HTTP exception rendering.
- [ ] Preserve existing throttle-specific 429 behavior.
- [ ] Use `ApiErrorFormatter` in session/first-party middleware too.
- [ ] Make empty `errors` and `meta` match the shared JSON object contract.

Verbatim audit snippet; adapt local variable names/arguments to the existing handlers:

```php
return ApiErrorFormatter::response($message, $status, $code)
    ->withHeaders($e->getHeaders());
```

**Tests to add/update**

- `tests/Feature/Api/V1/ApiExceptionHandlerTest.php`.
- `tests/Feature/Exceptions/HandlerRenderingTest.php`.
- `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`.
- `tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php`.
- **New (proposed):** `tests/Feature/Api/Middleware/AuthenticationBoundaryResponseTest.php`.
- [ ] Port the dropped-header probe at line 126.
- [ ] Assert a real 405 response includes the appropriate `Allow` methods.
- [ ] Assert conflict responses carrying `Retry-After` preserve the actual header at runtime.
- [ ] Retain 429 retry/rate-limit headers.
- [ ] Inspect raw JSON types: decoding only to associative arrays can conceal the distinction between `{}` and `[]`.
- [ ] Verify authentication-boundary errors share the same envelope and retain correct status codes.

**Verification**

- [ ] Compare documentation and actual headers; schema assertions alone are insufficient.
- [ ] Confirm generic 5xx bodies still hide internal exception details.

## P3.3 — Keep filters, sort, and page size in pagination links

**Files**

- `app/Services/Dashboard/UserProjectListingService.php:51`.
- `app/Services/Project/MeetingService.php:40` — inspect for the same issue; modify if applicable.
- `app/Repository/Admin/UserRepository.php:33` — inspect for the same issue; modify if applicable.
- Supporting request/DTO/controller call sites that construct the validated listing query. Locate callers of `paginateUserProjects` before changing its signature.
- `app/Http/Requests/Api/V1/Concerns/InteractsWithApiQueryPagination.php` — preserve bounded page-size behavior.

**Implementation checklist — audit requirement**

- [ ] Pass validated canonical query parameters into the listing service.
- [ ] Exclude the current `page` parameter before appending query data.
- [ ] Apply the query to paginator-generated next/previous links.
- [ ] Inspect meeting/admin-user pagination and repair equivalent loss where found.

Verbatim inline audit call:

```php
$paginator->appends($queryParameters)
```

The audit also permits `withQueryString()` as a smaller alternative where request coupling is acceptable. Prefer the validated canonical-query approach unless there is a concrete reason to retain request coupling.

**Tests to add/update**

- `tests/Unit/Services/Dashboard/UserProjectListingServiceTest.php`.
- `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php`.
- `tests/Feature/Api/V1/Meetings/MeetingReadTest.php`.
- `tests/Feature/Api/V1/Admin/UsersTest.php`.
- [ ] Port the query-loss probe at line 113.
- [ ] Follow `links.next` from a filtered/sorted, small-page-size request and assert the returned collection still satisfies those constraints.
- [ ] Follow previous links where present and test nested query encoding.
- [ ] Assert unknown/unvalidated query inputs are not unnecessarily echoed into links.
- [ ] Preserve page-size limits and authorized collection scoping.

**Verification**

- [ ] Follow server-generated links through HTTP instead of only comparing a URL string.
- [ ] Keep query-count and realistic-data checks for P4.4.

## P3.4 — Remove accidental subscription form routes

**Files**

- `routes/api/v1/users.php:27`.
- `app/Http/Controllers/Api/V1/SubscriptionController.php` — confirm the supported operations.
- `tests/Feature/Api/V1/Subscriptions/SubscriptionManagementTest.php`.
- `tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php`.

**Implementation checklist — audit requirement**

- [ ] Use an API singleton or explicitly register the four supported API operations.
- [ ] Retain each operation's existing/updated auth, scope, subscription, idempotency, and throttle middleware.
- [ ] Remove unsupported create/edit form routes from runtime and documentation.

Verbatim inline audit replacement:

```php
Route::apiSingleton('subscription', SubscriptionController::class)->creatable()
```

This is a route-chain fragment. Retain the actual middleware chain and terminate the completed statement appropriately.

**Tests and verification**

- [ ] Port the accidental-form-route probe at line 138.
- [ ] Assert create/edit form routes are not registered and no request dispatches a missing controller method.
- [ ] Preserve show/store/update/destroy behavior and method-specific access checks.
- [ ] Compare `route:list` output to OpenAPI route coverage.

## P3.5 — Separate domain validation from infrastructure failures

**Files**

- `app/Services/FileService.php:117`.
- `app/Services/User/UserService.php:54`.
- Supporting error handling: `app/Exceptions/Traits/HandlesApiExceptions.php` and `app/Exceptions/Handler.php`.

**Implementation checklist — audit requirement**

- [ ] Rethrow or preserve domain validation separately.
- [ ] Report/map storage and other infrastructure exceptions to appropriate safe 5xx API responses.
- [ ] Stop converting broad password-update exceptions into validation 422 responses.
- [ ] Preserve actionable validation errors for genuinely invalid client input.

**Tests to add/update**

- `tests/Feature/Api/V1/Users/UserAvatarTest.php`.
- `tests/Feature/Api/V1/User/PasswordUpdateTest.php`.
- `tests/Feature/Api/V1/ApiExceptionHandlerTest.php`.
- `tests/Feature/Exceptions/HandlerReportingTest.php`.
- **New (proposed):** `tests/Unit/Services/FileServiceFailureTest.php`.
- [ ] Valid input plus storage outage produces safe 5xx and a reported diagnostic.
- [ ] Invalid input still produces the expected domain validation response.
- [ ] Password-operation infrastructure failures do not masquerade as invalid passwords.
- [ ] Client bodies hide internal details and phase 1 sanitization protects logged context.

**Verification**

- [ ] Exercise both validation and infrastructure branches through real API exception rendering.

## P3.6 — Synchronize public contracts and define compatibility decisions

**Existing files**

- `app/Providers/RouteServiceProvider.php` — explicit version registration.
- `routes/api/v1.php` and affected route modules.
- `app/Providers/ScrambleServiceProvider.php`.
- `app/Documentation/Transformers/PublicApiMiddlewareResponses.php`.
- `api.json` — generated API export.
- `tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php`.
- `tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php`.

**New (proposed) documentation**

- `docs/API_COMPATIBILITY.md`.

**Implementation checklist — audit requirement**

- [ ] Keep versioning explicit and define a deprecation/migration policy for already-consumed public v1 contracts.
- [ ] Do not silently remove consumed methods/endpoints or change payload meaning.
- [ ] Update API documentation and tests for method, no-op, header, pagination, and error changes.

**Implementation elaboration**

- [ ] Record whether the affected v1 surface has consumers and select a compatible transition for any breaking change.
- [ ] Include phase 1 email/account-security and collaborator visibility changes.
- [ ] Include phase 2 unknown/pending operation representations and optimistic-concurrency behavior.
- [ ] If selecting If-Match, document emitted validators, missing/stale preconditions, and expected status/header behavior; test a complete read-modify-write flow.
- [ ] Update documentation source attributes/transformers and generate `api.json`; avoid manual-only edits to generated JSON.
- [ ] Review documentation/runtime parity and actual HTTP behavior together.

**Verification commands**

```sh
php artisan route:list --path=api -vv
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Contracts/Docs
php artisan scramble:export
```

The export command follows the repository's existing documentation workflow. Inspect its output/diff and confirm it matches the chosen contract.

## P3.7 — Optional resource naming improvements

This task preserves the audit's low priority and conditional wording. Record a deliberate deferral if current names are retained; security/reliability acceptance must not depend on renaming them.

**Files**

- `routes/api/v1/projects/tasks.php:25`.
- `routes/api/admin/v1.php:51`.
- `routes/web/v1.php:49`.
- Locate the corresponding controllers, requests, policies, and tests from these route registrations before changing them.

**Implementation checklist — conditional audit guidance**

- [ ] If changing the public contract, represent task assignment through task-assignee resources.
- [ ] For substantial bulk work, consider a bulk-deletion job resource with observable progress/outcome.
- [ ] Represent two-factor status through a status resource if replacing `fetch-user`.
- [ ] Apply the P3.6 compatibility policy and preserve middleware/security boundaries.

**Implementation elaboration: candidate shapes, not verbatim audit endpoints**

- `POST /api/v1/projects/{project}/tasks/{task}/assignees`.
- `DELETE /api/v1/projects/{project}/tasks/{task}/assignees/{user}`.
- `POST /api/v1/admin/bulk-deletion-jobs` with a read operation for job status, using the current versioned admin prefix.
- A two-factor status resource within the existing first-party route boundary; inspect its actual prefix before selecting a URI.

**Tests and verification**

- [ ] If implemented, assert method/resource behavior, policy parity, scoped targets, and documented compatibility.
- [ ] If deferred, record the decision and leave existing routes intact.
- [ ] Do not force DELETE bodies or introduce asynchronous job machinery for trivial operations merely to improve naming.

## Phase verification and acceptance

Run from the repository root with the isolated test configuration:

```sh
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Projects/ProjectFeatureTest.php
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Tasks/TaskTest.php
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/ApiExceptionHandlerTest.php
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Subscriptions
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Contracts/Docs
composer test
composer stan
```

- [ ] Methods, no-op behavior, error types, and protocol headers match runtime documentation.
- [ ] Pagination links preserve the authorized selected collection.
- [ ] Infrastructure failures receive appropriate 5xx behavior.
- [ ] Subscription form routes are absent.
- [ ] Compatibility decisions cover phase 1/2 contract changes and any optional renaming.
- [ ] Report changed files, test outcomes, generated documentation changes, and explicitly deferred naming choices.
