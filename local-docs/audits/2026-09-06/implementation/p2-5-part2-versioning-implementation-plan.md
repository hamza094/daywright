# P2.5 Part 2: Collaborative editing versioning

## Goal and contract

Prevent a client from silently overwriting task or project data after another edit has committed. Keep the existing Form Request → DTO → service → API resource flow.

Use a numeric `version` in task and project representations and require that version in every collaborative update request. On a stale version, return HTTP `409 Conflict` in the existing API error envelope. A missing version is invalid; there is no optional-version compatibility window because it would let clients bypass the protection.

This body-field contract is straightforward for the current API. HTTP `ETag`/`If-Match` is a standards-based alternative, but supporting it would add header parsing and response-header behavior without improving this application’s current workflow. Do not implement both contracts.

## Scope

Version the public collaborative-edit operations:

- Task field and status updates through `TaskService::updateTask()`.
- Project field updates through `ProjectService::updateProject()`.
- Project stage updates through `ProjectService::updateStageStatus()`.

These operations all compare the submitted version and increment it exactly once on a successful business change. Inventory other task/project write routes during implementation. Include a route only if it changes state represented as part of this collaborative edit contract; do not mechanically add locks or version increments to unrelated jobs and actions. Derived fields such as calculated health scores should not invalidate an edit unless they are made client-editable.

## Implementation

### 1. Schema

Add one migration for both tables with non-null `unsignedInteger('version')->default(1)`. This gives existing rows a usable initial version and keeps schema rollout in one small change. Do not edit historical create-table migrations.

### 2. API boundary

- Add `version` to the public task and project resources so reads and update responses provide the version the client must send next.
- Add required integer `version >= 1` validation to `TaskUpdateRequest`, `ProjectUpdateRequest`, and the project `StageRequest`.
- Pass the validated value through the existing DTOs. Keep it separate from business attributes so it cannot be mass-assigned as an ordinary field.
- Keep each update request’s empty-business-payload check working: `version` alone is not an edit.
- Update Scramble annotations/export and document the request/response example and `409` error for affected endpoints.

Making the version required changes the existing v1 request contract. Coordinate rollout with the frontend/API consumers and record the compatibility decision in the API contract work (P3.6). Do not silently accept missing versions during rollout; if old clients must remain supported, gate collaborative updates until they can send the version.

### 3. Services and conflict response

For each scoped update, use the Part 1 transaction/locking pattern:

1. Begin the transaction and reload the row with `lockForUpdate()`.
2. Compare the submitted version with the freshly locked row. If they differ, raise a domain `App\Exceptions\ApiException` subtype with status 409 and a stable error code.
3. Validate state transitions against the locked model and apply all changes to it.
4. Increment and save the version once in the same transaction as the business change.
5. Return the updated model; send project notifications only after the transaction commits.

Register/format the conflict through the existing `HandlesApiExceptions` and `ApiErrorFormatter` path. Keep the standard `{message, code, errors, meta}` response shape; do not hand-render a separate JSON schema from the exception. Return a safe public message and, if useful to clients, include only resource context and the current version in `meta` so the client can reload. Do not expose a raw exception message or require clients to interpret both expected and current versions to recover.

The version check and row lock address complementary cases: the lock serializes requests currently executing, while the version detects that a client’s read became stale before it submitted an edit. Do not hold the transaction open while sending notifications or doing other external work.

### 4. Tests and verification

Add focused feature tests under `tests/Feature/Api/V1/Tasks/` and `tests/Feature/Api/V1/Projects/` for:

- Reads and successful updates include the version; successful edits increment it once.
- Matching-version task, project-field, and project-stage updates succeed.
- Missing/invalid versions fail validation; stale versions return the standard 409 envelope and do not change business data or send success notifications.
- Two edits submitted with the same version cannot both succeed; the loser receives 409.
- A stale task/project state transition cannot revive or bypass a terminal state.
- Version-only payloads remain empty edits.

Use the normal test database for request and contract behavior. Verify row-lock concurrency separately with multiple processes on the intended production database engine (MySQL in this project’s production-like CI); SQLite is not evidence that `lockForUpdate()` serializes workers. Avoid separate service unit tests that duplicate the same HTTP behavior unless a specific service-only boundary needs coverage.

Run the focused feature tests, relevant OpenAPI parity/export checks, then the applicable project release gates. Record database-engine concurrency results separately from SQLite results.

## Rollout and acceptance

Deploy the additive migration before code that requires `version`, then deploy backend and compatible clients together (or keep collaborative update endpoints gated until clients send versions). Existing rows begin at version `1`.

P2.5 Part 2 is complete when all scoped updates require and check versions, successful changes increment once atomically, stale requests return the standard 409 response, the focused behavior tests pass, and production-engine concurrency is verified. Revert through a forward migration if the column must be removed after deployment; do not rely on rolling back a migration after newer code or data has used it.
