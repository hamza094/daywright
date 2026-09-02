# API Documentation Synchronization and Production Readiness Plan

## Objective

Bring the public Daywright v1 OpenAPI document into strict alignment with runtime routes, validation, resources, responses, exception handling, and backend engineering guidelines.

This plan covers only routes included in the third-party public Scramble document. Session-authenticated, first-party-authenticated, admin, webhook, and other explicitly excluded routes remain outside this specification.

## Definition of Done

The work is complete only when all of the following are true:

- A freshly generated `api.json` matches runtime behavior for authentication, parameters, request bodies, response bodies, status codes, nullability, enums, and formats.
- All public operations have stable, unique operation IDs and declared top-level tags.
- Public error responses use the same envelope and JSON value types emitted by `ApiErrorFormatter`.
- The production documentation access policy is explicit and tested.
- The backend guidelines describe the conventions actually used by the public API.
- Documentation contract tests pass and CI detects an out-of-date committed specification.

## Resolved Technical Decisions

These items do not require further product input:

1. `TaskSystemStatus` is already a native integer-backed PHP enum. Use `Rule::enum(TaskSystemStatus::class)` and remove the nonexistent `TaskSystemStatusRuleTransformer` registration.
2. Notifications are cursor-paginated. Their supported pagination parameters are `cursor` and `per_page`, not `page`.
3. Existing public DELETE operations return `200 OK` with `{ "message": string }`. Preserve this v1 contract and update the guidelines and overview instead of changing response statuses to `204`.
4. `GET /v1/scopes` is a public operation and must explicitly override the document's global Bearer requirement.
5. Keep the currently installed Scramble version during this work. Use the supported `@unauthenticated` annotation rather than combining this audit with a package upgrade.

## Product Decisions Required

### 1. PII visibility for `GET /users/{user}`

Recommended decision: return a restricted public user representation to third-party clients.

The current endpoint requires only `team:read` but returns email, mobile, address, internal numeric ID, and extended profile information without an owner or project-membership authorization check.

Choose and document one contract:

- **Restricted public profile (recommended):** Return a dedicated public resource containing only approved identity and collaboration fields. Add authorization where project or team membership is required.
- **Full profile:** Explicitly approve every exposed field, add an appropriate authorization policy, and document the visibility rules for consumers.

Add endpoint tests that prove unauthorized users cannot retrieve protected profile data.

### 2. Production documentation access

Choose and test one publication model:

- **Static public specification (recommended for third-party docs):** Keep `RestrictedDocsAccess` closed in production, generate `api.json` during deployment, and publish the artifact through the approved documentation host.
- **Gated runtime documentation:** Define and test the `viewApiDocs` gate for the intended administrators or documentation users.
- **Public runtime documentation:** Explicitly allow unauthenticated documentation access after confirming the document contains no internal routes or sensitive examples.

Do not consider documentation production-ready while the selected access model is undefined.

## Phase 1: Configuration, Overview, and Guidelines

### `config/scramble.php`

- Keep the effective server base as `/api` and verify that documented paths remain under `/v1`.
- Keep the title resolved as `DayWright` or set it explicitly if deployment environments use different `APP_NAME` values.
- Continue reading the document version from `API_VERSION`.
- Replace `require_once` with `require` when loading `scramble_overview.php` so repeated configuration loading always returns the overview string.
- Implement the selected production documentation access policy.

### `.env.example`

- Add `API_VERSION=0.3.1` or the approved current documentation version.
- Document any environment variable introduced for documentation access or publication.

### `config/scramble_overview.php`

- Retain concise Bearer-token usage instructions, token-scope semantics, and how a third-party consumer obtains access.
- Remove detailed endpoint documentation and examples for excluded login, registration, session, OAuth, two-factor, and token-management operations.
- Remove the claim that public mutations return `204 No Content` while the v1 implementation returns `200` message envelopes.
- Ensure every example uses the `/api/v1` base path and only references operations present in the public specification.

### Backend guideline sources

Update `.ai/guidelines/backend-guidelines.md` and `.github/copilot-instructions.md` together:

- Use `#[Endpoint(operationId: 'resource.action')]` for stable public operation IDs. Do not describe it as an `@operationId` attribute.
- State that tags are resolved centrally by `ScrambleServiceProvider`; do not manually add `@tags` to public controllers.
- Define `data` as the canonical payload envelope and `{ "message": string }` as the command-only response envelope.
- State that current public DELETE operations return `200` with a message body.
- Allow `@unauthenticated` for public operations that are included beneath global document security.
- State that error responses are documented only when inferred or explicitly ensured by the provider. Do not claim all error statuses are automatically injected.
- Keep Form Request-to-DTO and dedicated API Resource requirements synchronized across both guideline files.

Update or archive `local-docs/api-restructure/scamble-docs-plan.md` so its `204`, `@unauthenticated`, and global-error-injection instructions cannot be mistaken for current policy.

## Phase 2: Scramble Provider Synchronization

### Authentication and tags

- Add `@unauthenticated` to `ApiScopeController::index()`.
- Map `api/v1/scopes` to the existing `API Tokens` tag in `resolvePublicApiTag()`.
- Ensure every operation tag is present in `publicApiTagDefinitions()` and remove the undeclared `Public API` tag from the generated document.
- Verify that `/v1/scopes` has operation-level `security: []` while protected operations inherit Bearer security.

### Shared error responses

- Explicitly attach `PublicRateLimitError` (`429`) to every documented public operation because every route uses `throttle:api`.
- Preserve the existing explicit `500` response policy.
- Convert every empty `errors` and `meta` example to an object, including property examples and complete envelope examples. Generated JSON must contain `{}`, not `[]`.
- Document the `Retry-After` header on the shared `429` response if supported by the current response component implementation.
- Inventory public controllers, policies, route binding, validation, `abort()` calls, and thrown API exceptions. Document only the operation-specific `400`, `403`, `404`, `409`, `422`, and `503` responses that runtime can actually produce.

### Query parameters

- Add `cursor` and `per_page` to `/v1/notifications`; do not add `page`.
- Add descriptions, realistic examples, defaults, and validation constraints to all manually injected parameters, not only notifications.
- Pagination schemas must preserve `page >= 1`, `1 <= per_page <= 100`, and the endpoint-specific `per_page` default.
- Date query parameters must use the correct `date` or `date-time` format.
- Verify the descriptions and array schema for `/v1/projects/{project}/insights` `sections[]`.
- Refactor `makeQueryParameter()` only as much as needed to accept this metadata without duplicating parameter construction.

### Dead configuration

- Remove the `TaskSystemStatusRuleTransformer` registration.
- Confirm no other registered Scramble transformer, extension, or schema name points to a missing class.

## Phase 3: Public Form Request Contracts

### Task requests

- Add `string` to `TaskRequest.title` and `TaskUpdateRequest.title` runtime validation.
- Remove `@var string` overrides from title fields so Scramble retains `minLength` and `maxLength`.
- Add `string` to `TaskUpdateRequest.description`. Add `nullable` only if explicit JSON `null` is an accepted update value.
- Add `@format date-time` to `TaskUpdateRequest.due_at` while retaining the ISO-8601 custom runtime rule.
- Replace `Rule::in(TaskSystemStatus::all())` with `Rule::enum(TaskSystemStatus::class)` and remove `@var int` from `status_id`.
- Verify that `status_id` generates integer enum values `1`, `2`, `3`, `4`, and `5`.
- Verify that `notified` generates the four `TaskDueNotifies` string values.

### Project requests

- Remove type-redefining `@var` annotations from `ProjectStoreRequest.about`, `notes`, and `tasks` where standard validation already defines the type.
- Let `nullable|string|max:250` infer both nullability and `maxLength` for `notes`; do not immediately replace it with `@var string|null`.
- Verify that `about` retains `minLength: 15` and `tasks` retains `maxItems: 3`.
- Verify nested `tasks.*.title` remains required, distinct, and constrained to 5-55 characters.

### User requests

- Remove the `@var string` override from nullable `UserRequest.mobile`.
- Verify that the generated schema permits `null`, remains a string otherwise, and documents the expected phone-number pattern or business format.

### Complete public-request audit

- Review every included public Form Request for `@var` annotations that replace useful `min`, `max`, enum, nullable, array, or format inference.
- Review custom validation rules and dynamic closures. Add a transformer or explicit schema hint only when the generated artifact proves native inference is insufficient.
- Do not add redundant annotations for rules Scramble already documents correctly.

## Phase 4: Public API Resource Contracts

### Date and URL formats

- Add `@format date-time` to every ISO-8601 response timestamp, including notification, invitation, conversation, project, task, subscription, receipt, activity, and profile resources.
- Add `@format uri` to URL properties still missing it, including `ReceiptResource.receipt_url`.
- Preserve nullability on optional timestamps while adding their format.

### Receipt resource

- Add `@mixin \Laravel\Paddle\Receipt` to `ReceiptResource`.
- Verify that `id` and `quantity` generate as integers. Add explicit `@var int` only if model inference still produces strings.
- Verify that `tax` and `amount` remain decimal strings and `currency` is documented as an ISO-4217 code.

### Task resources

- Replace the false `none`, `daily`, `weekly`, and `monthly` reminder descriptions.
- Add a PHPStan literal-string union so `TaskResource.notified` generates the four allowed values as an OpenAPI enum while still allowing `null` when runtime allows it.
- Type `TaskStatusIndexResource.due_notifies` as an array whose items use the same four-value enum.

### User resources

- Make `UserInfoResource.mobile` nullable in the schema.
- Make `UserProfileResource.verified` optional, nullable, and `date-time`.
- Make `UserProfileResource.timezone` explicitly non-null because runtime applies a fallback.
- Apply the approved PII visibility decision to the user-show response and describe any conditional fields accurately.

### Resource-wide verification

- Confirm conditional `when()` and `whenLoaded()` fields are optional rather than incorrectly required.
- Confirm nested relationships use dedicated API Resources and reusable component references.
- Confirm no token, secret, credential, private storage path, or internal queue/database detail appears in public schemas or examples.

## Phase 5: Controller Operation Contracts

Add explicit `#[Endpoint]` attributes for the four fallback operation IDs currently generated from route names:

- Project force deletion: `projects.forceDelete`
- Project invitation/user search: choose `invitations.searchUsers` or `projects.searchUsers` based on controller ownership
- Task member search: `tasks.searchMembers`
- User force deletion: `users.forceDelete`

Additional controller checks:

- Assert operation IDs are unique and follow `resource.action` naming.
- Keep Project update's already inferred shared `400` response; do not add `@response 400 ApiErrorFormatter`.
- Do not add public documentation annotations to excluded `TokenController` operations.
- Verify every success response status and envelope against the actual controller helper or Resource response.
- Resolve the `GET /users/{user}` authorization and PII contract before release.

## Phase 6: Automated Contract Verification

### Expand `ScrambleDocsTest`

Add assertions that prove:

- The document is OpenAPI 3.1, title is `DayWright`, version matches `API_VERSION`, and the effective base is `/api/v1`.
- `/v1/scopes` has `security: []`.
- Every protected operation inherits Bearer security and documents `401`.
- Every operation documents shared `429` and `500` responses.
- `/v1/notifications` parameters are exactly the supported filter aliases plus `cursor` and `per_page`, with no `page`.
- Error envelope examples serialize empty `errors` and `meta` as JSON objects.
- Every operation has a unique stable operation ID and every used tag has a top-level definition.
- Task status and reminder enums contain the exact runtime values.
- Receipt `id` and `quantity` are integers.
- Nullable mobile, notes, and verification fields permit `null`.
- Representative timestamps from each resource family use `format: date-time`.
- Request constraints include task title min/max, project about minimum, project notes maximum, and project tasks `maxItems`.

### Runtime contract tests

- Run exception-handler tests to confirm status codes, error codes, envelopes, empty-object serialization, and rate-limit metadata.
- Run affected task, project, notification, user, subscription, and receipt endpoint tests.
- Add or update tests for the selected PII authorization behavior and documentation-access policy.
- Run the complete test suite after targeted tests pass.

### Export and CI drift gate

Run:

```bash
php artisan scramble:export
php artisan test --filter ScrambleDocsTest
php artisan test --filter HandlerRenderingTest
php artisan test
```

Add a CI step that regenerates the specification and fails when the committed artifact is stale:

```bash
php artisan scramble:export
git diff --exit-code -- api.json
```

Use an OpenAPI 3.1-compatible validator or linter in CI if one is already approved for the project. Do not introduce a new documentation tool solely for this patch without team approval.

## Final Manual Review

After automated checks pass:

1. Open the generated documentation using the selected production access model.
2. Confirm all 52 expected public operations are present and excluded routes are absent.
3. Exercise one public operation and representative protected read/write operations through the documentation UI or an API client.
4. Verify Bearer authorization, public scope discovery, pagination examples, enums, date formats, nullable fields, error examples, and response envelopes.
5. Review generated client types for integer IDs, nullable values, enums, and reusable resource references.
6. Confirm that the published `api.json` is byte-for-byte the artifact verified by CI.

## Release Gate

Do not mark the public API documentation production-ready until:

- Both product decisions are recorded.
- All targeted and full tests pass.
- A fresh export produces no uncommitted `api.json` diff.
- No undefined tags, fallback operation IDs, stale query parameters, undocumented global `429`, false enum values, incorrect nullability, or missing representative formats remain.
- Backend guidelines, overview content, runtime behavior, tests, and the generated OpenAPI document describe the same public v1 contract.
