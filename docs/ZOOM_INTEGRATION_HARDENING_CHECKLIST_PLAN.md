# Zoom Integration Hardening Checklist Plan

Created: 2026-06-01

This plan converts the Zoom integration review into a phased implementation roadmap.
The goal is to make DayWright's Zoom meeting, OAuth, webhook, and Saloon-backed transport layers production-safe without forcing a broad rewrite.

Work through the phases in order.
Keep behavior changes small, validate each phase in isolation, and avoid mixing transport refactors with unrelated product work.

## Goal

- Harden the Zoom integration at the HTTP boundary, domain boundary, and provider boundary.
- Remove fragile assumptions around webhook payload shape, event ordering, token refresh, and request execution context.
- Establish one reliable integration pattern that can be reused for future third-party providers.
- Reach a clear production-readiness gate instead of relying on ad hoc confidence.

## Out Of Scope

- Redesigning the meeting product experience.
- Rewriting the existing Zoom integration from scratch.
- Replacing Saloon with Laravel HTTP client.
- Broad controller or route refactors that do not directly reduce Zoom integration risk.
- Introducing new third-party providers before the Zoom integration reaches a stable release gate.

## Production Ready Only When

- Webhook signature verification uses the raw request body and passes provider validation flows.
- Webhook processing is idempotent, field-whitelisted, and tolerant of duplicate, delayed, and stale deliveries.
- OAuth and access-token refresh flows are safe under concurrency and do not lose rotated refresh tokens.
- Saloon request classes are transport-tested for endpoint, method, headers, body, and exception behavior.
- Zoom service operations return explicit domain results instead of mixed transport responses.
- Logging, retry, and failure visibility are strong enough to debug production incidents without guesswork.
- The targeted Zoom test suite passes and covers both happy paths and critical failure paths.

## Current Risk Summary

- Webhook signature verification rebuilds the payload from parsed request data instead of using the raw body.
- The webhook update path persists provider fields too loosely.
- Start and end event jobs are not fully tolerant of missing optional fields or out-of-order deliveries.
- Token refresh is not protected against concurrent refresh attempts.
- The service contract mixes DTO results with raw transport responses.
- Current tests rely heavily on a fake Zoom service and do not prove the Saloon transport layer end to end.
- Some integration code still depends on HTTP auth context or Windows-specific assumptions.

## Hardening Themes

### Boundary Safety

- Verify external input before it reaches internal write paths.
- Normalize provider payloads into internal command shapes.
- Reject or ignore unsupported provider fields instead of persisting them implicitly.

### Deterministic Behavior

- Make duplicate and out-of-order webhooks safe.
- Make token refresh behavior deterministic under concurrency.
- Make integration failures observable and classifiable.

### Reusable Integration Patterns

- Keep provider schema separate from domain schema.
- Keep transport concerns inside Saloon connectors and requests.
- Keep domain orchestration inside services or actions.
- Keep response normalization explicit and typed.

## Phase Summary

| Phase | Focus                                                     | Priority | Gate                                                          |
| ----- | --------------------------------------------------------- | -------- | ------------------------------------------------------------- |
| 0     | Freeze the current contract and risk surface              | Critical | Must finish first                                             |
| 1     | Harden the webhook HTTP boundary                          | Critical | Must finish before payload refactors                          |
| 2     | Normalize webhook payloads and job behavior               | Critical | Must finish before production rollout                         |
| 3     | Harden OAuth and token lifecycle behavior                 | Critical | Must finish before high-concurrency usage                     |
| 4     | Tighten the Saloon transport layer and service contracts  | High     | Must finish before using this as a reusable provider template |
| 5     | Add observability, failure handling, and operator tooling | High     | Must finish before release gate                               |
| 6     | Final verification and release gate                       | Critical | Final go or no-go decision                                    |

## Phase 0 - Freeze The Current Contract And Risk Surface

Priority: Critical  
Gate: Must finish first

Status:

- Implemented as a documentation baseline in `docs/ZOOM_INTEGRATION_PHASE0_BASELINE.md`.

Why this phase comes first:

- The current integration already works for core happy paths, so the first job is to freeze what must not regress.
- Zoom hardening touches webhooks, OAuth, services, and tests; the current contract must be explicit before code moves.
- This phase prevents later changes from quietly changing meeting response, webhook, or OAuth behavior.

Review first:

- `app/Http/Integrations/Zoom/ZoomConnector.php`
- `app/Services/Zoom/ZoomService.php`
- `app/Services/Project/MeetingService.php`
- `app/Http/Middleware/VerifyZoomWebhook.php`
- `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`
- `app/Jobs/Webhooks/Zoom/*.php`
- `tests/Feature/Api/V1/Meetings/*.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`
- `tests/Feature/Api/Auth/Zoom/*.php`

Step-by-step tasks:

- [x] Freeze the current public API surface for meeting CRUD, Zoom connect, Zoom token retrieval, and webhook endpoints.
- [x] List which Zoom webhook events are intentionally supported now and which are intentionally unsupported.
- [x] Document the current response contracts for meeting create, update, delete, webhook accepted, and OAuth callback success and failure.
- [x] Identify every place where provider payload shape currently leaks directly into model writes.
- [x] Identify every place where current tests use a fake provider instead of testing the Saloon request layer.
- [x] Decide which current behaviors are compatibility requirements versus hardening targets.

Phase 0 outputs:

- `docs/ZOOM_INTEGRATION_PHASE0_BASELINE.md` freezes the current supported Zoom API surface.
- The baseline records the current meeting, OAuth, token, and webhook response contracts.
- The baseline records the known schema and transport leak points that later phases are expected to change.
- The baseline records where feature coverage relies on `ZoomServiceFake` versus direct Saloon-backed tests.

Exit criteria:

- The supported Zoom surface is explicit.
- The current failure paths and compatibility assumptions are written down.
- Later phases can change internals without accidentally widening scope.

## Phase 1 - Harden The Webhook HTTP Boundary

Priority: Critical  
Gate: Must finish before payload refactors

Status:

- Implemented in the webhook middleware and focused webhook test suite.

Why this phase comes second:

- Webhooks are the most exposed external input boundary.
- If verification is wrong at the HTTP edge, every deeper safeguard is weaker than it looks.
- The route and middleware layer should prove authenticity before domain code touches the payload.

Files likely involved:

- `app/Http/Middleware/VerifyZoomWebhook.php`
- `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`
- `routes/api/v1.php`
- `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`

Step-by-step tasks:

- [x] Change signature verification to hash the raw request body exactly as delivered instead of re-encoding `request()->all()`.
- [x] Keep strict required-header checks for request id, signature, and timestamp.
- [x] Keep timestamp skew validation and make the accepted skew explicit.
- [x] Add support for Zoom endpoint validation handshake if Event Subscriptions require it in this environment.
- [x] Keep mapping Zoom request id onto the idempotency header so HTTP-level duplicate deliveries remain deduplicated.
- [x] Ensure invalid signatures, missing headers, stale timestamps, and validation-handshake failures return deterministic API-safe responses.

Phase 1 outputs:

- `VerifyZoomWebhook` now hashes the exact raw request body for signature verification.
- Endpoint validation requests are handled before controller validation and job dispatch.
- The middleware still maps `x-zm-request-id` onto the configured idempotency header after verification.
- Focused middleware and webhook tests now cover raw-body verification and endpoint validation behavior.

Recommended tests:

- Valid signature with raw JSON body ordering preserved.
- Invalid signature rejection.
- Missing request id rejection.
- Stale timestamp rejection.
- Duplicate request id replay handling.
- Endpoint validation request handling if enabled.

Exit criteria:

- The webhook boundary authenticates the exact bytes Zoom sent.
- Duplicate delivery protection still works at the HTTP layer.
- The project can pass Zoom's webhook verification flow without custom manual steps.

## Phase 2 - Normalize Webhook Payloads And Job Behavior

Priority: Critical  
Gate: Must finish before production rollout

Status:

- Implemented in the webhook controller, webhook jobs, started-notification null handling, and focused webhook/job tests.

Why this phase comes third:

- Once the HTTP boundary is trusted, the next risk is unsafe payload handling and brittle event processing.
- Webhook events are inherently unordered and retry-prone, so jobs must be built for that reality.
- This phase closes the biggest remaining production risks in the current implementation.

Files likely involved:

- `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`
- `app/Jobs/Webhooks/Zoom/UpdateMeetingWebhook.php`
- `app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php`
- `app/Jobs/Webhooks/Zoom/MeetingEndsWebhook.php`
- `app/Jobs/Webhooks/Zoom/DeleteMeetingWebhook.php`
- `app/Models/Meeting.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`

Step-by-step tasks:

- [x] Replace pass-through provider payload writes with a strict whitelist mapper for local meeting fields.
- [x] Ignore unknown or unsupported provider keys instead of trying to persist them.
- [x] Make `meeting.started` and `meeting.ended` handlers safe when optional timestamps are missing.
- [x] Treat missing local meetings for stale, deleted, or out-of-order events as idempotent no-ops instead of hard failures.
- [x] Keep duplicate event handling safe under queue retries and overlapping workers.
- [x] Make status transitions explicit so duplicate started or ended events short-circuit cleanly.
- [x] Review whether meeting broadcasts and notifications should only fire after a verified state transition.
- [x] Consider introducing a small normalized webhook DTO or command payload for each supported event type.

Recommended tests:

- Update webhook with extra unsupported fields does not fail and does not persist them.
- Started event without `start_time` remains safe.
- Ended event without optional timestamps remains safe.
- Started event for a missing meeting is ignored safely.
- Duplicate started or ended deliveries do not re-send notifications.
- Update, delete, start, and end jobs remain safe under retries.

Exit criteria:

- Webhook jobs only write internal allowlisted fields.
- Event processing is idempotent and tolerant of delivery disorder.
- Notification and broadcast side effects happen only on valid state changes.

## Phase 3 - Harden OAuth And Token Lifecycle Behavior

Priority: Critical  
Gate: Must finish before high-concurrency usage

Status:

- Implemented in the OAuth controller, Zoom service refresh path, user Zoom credential helpers, and focused OAuth/service tests.

Why this phase comes fourth:

- A webhook-safe integration can still fail badly if token rotation or refresh is unsafe.
- Access-token refresh bugs often surface only under real concurrency and are hard to debug after release.
- This phase makes the Zoom account connection durable instead of merely functional.

Files likely involved:

- `app/Http/Controllers/Api/OAuth/ZoomAuthController.php`
- `app/Services/Zoom/ZoomService.php`
- `app/Models/User.php`
- `app/DataTransferObjects/Zoom/AccessTokenDetails.php`
- `tests/Feature/Api/Auth/Zoom/ZoomOAuthRedirectTest.php`
- `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`
- `tests/Unit/Services/Zoom/*.php`

Step-by-step tasks:

- [x] Protect per-user token refresh with a lock so only one request refreshes at a time.
- [x] Reload the latest user token state after the refresh lock is acquired.
- [x] Persist rotated access and refresh tokens atomically.
- [x] Decide how unauthorized or revoked Zoom accounts should be cleared or flagged locally.
- [x] Keep PKCE verifier and OAuth state handling explicit and time-bounded.
- [x] Verify callback behavior when state is missing, expired, replayed, or denied by the user.
- [x] Define a clear policy for what happens when Zoom returns an expired or unusable refresh token.

Recommended tests:

- Callback success persists the full token bundle.
- Callback failure does not mutate the user token state.
- Missing or expired cached verifier fails safely.
- Simulated concurrent refresh updates do not lose the newest token pair.
- Unauthorized provider responses are surfaced consistently.

Exit criteria:

- Token refresh is safe under concurrent requests.
- Zoom OAuth state and verifier handling is deterministic.
- Revoked or unusable Zoom credentials fail cleanly and predictably.

## Phase 4 - Tighten The Saloon Transport Layer And Service Contracts

Priority: High  
Gate: Must finish before using this as a reusable provider template

Why this phase comes fifth:

- The current Saloon structure is a good foundation, but some contracts are still too loose for long-term maintenance.
- This phase turns a working integration into a reusable pattern for future providers.
- It also reduces coupling between the transport layer and the HTTP runtime.

Files likely involved:

- `app/Http/Integrations/Zoom/ZoomConnector.php`
- `app/Http/Integrations/Zoom/Requests/*.php`
- `app/Services/Zoom/ZoomService.php`
- `app/Interfaces/Zoom.php`
- `app/DataTransferObjects/Zoom/*.php`
- `config/saloon.php`
- `tests/Unit/Services/Zoom/*.php`

Step-by-step tasks:

- [ ] Replace mixed transport return types with explicit domain results for update and delete operations.
- [ ] Keep provider response mapping strict enough to fail loudly on malformed critical payloads.
- [ ] Remove direct dependence on `auth()->id()` from Saloon request classes and pass provider-aware limiter identity explicitly.
- [ ] Review whether request-level rate limits should be centralized or kept per request.
- [ ] Standardize connector exception taxonomy for unauthorized, not found, rate-limited, user-correctable, and upstream-failure cases.
- [ ] Add transport-level tests for endpoint resolution, request body normalization, headers, and exception mapping.
- [ ] Fix cross-platform configuration assumptions such as the integrations path casing in `config/saloon.php`.

Recommended tests:

- Create, update, delete, token, and OAuth requests assert endpoint, method, and payload.
- Connector exception mapping is correct for 403, 404, 429, and 5xx responses.
- Rate-limit prefixing works in non-HTTP execution contexts.
- DTO parsing rejects malformed critical responses where silent fallback would be dangerous.

Exit criteria:

- The Zoom contract is typed and predictable.
- Saloon request classes are reusable outside controller-driven HTTP requests.
- The transport layer is proven by direct tests instead of only service fakes.

## Phase 5 - Add Observability, Failure Handling, And Operator Tooling

Priority: High  
Gate: Must finish before release gate

Why this phase comes sixth:

- Production readiness depends on diagnosis speed, not only code correctness.
- Third-party API integrations fail in ways that require strong logs, metrics, and retry visibility.
- This phase turns unknown failures into actionable incidents.

Files likely involved:

- `app/Exceptions/Integrations/Zoom/*.php`
- `app/Exceptions/Handler.php`
- `app/Jobs/Webhooks/Zoom/*.php`
- `config/logging.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`
- `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`

Step-by-step tasks:

- [ ] Standardize structured logs for Zoom request failures, webhook ignores, retries, and final job failures.
- [ ] Ensure provider, operation name, meeting id, request id, and user id are available in relevant log contexts.
- [ ] Decide whether ignored stale events should be logged at `info` or `warning` and keep that policy consistent.
- [ ] Make queue retry and terminal failure behavior explicit for each Zoom webhook job.
- [ ] Review whether rate-limit failures should expose retry-after metadata to clients or operators.
- [ ] Add an operator checklist for reconnecting revoked Zoom accounts and replaying failed webhook jobs.

Exit criteria:

- Zoom failures are diagnosable from logs without code spelunking.
- Queue and provider failures have consistent severity and metadata.
- Operators have a defined recovery path for common incidents.

## Phase 6 - Final Verification And Release Gate

Priority: Critical  
Gate: Final go or no-go decision

Why this phase comes last:

- A hardening plan is only complete when it ends with a strict release gate.
- Zoom integration confidence should come from focused verification, not intuition.
- This phase determines whether the integration is truly ready for production usage.

Validation checklist:

- [ ] Run the focused Zoom feature tests.
- [ ] Run the focused Zoom service and transport tests.
- [ ] Run the webhook middleware tests.
- [ ] Run formatting on dirty PHP files.
- [ ] Confirm no webhook code path persists unsupported provider fields.
- [ ] Confirm token refresh remains safe under concurrent access assumptions.
- [ ] Confirm meeting CRUD, OAuth connect, token retrieval, and webhook processing all use the documented contract.
- [ ] Review logs and failure messages for operator usefulness.

Suggested validation commands:

- `php artisan test --compact tests/Feature/Api/V1/Meetings`
- `php artisan test --compact tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`
- `php artisan test --compact tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`
- `php artisan test --compact tests/Feature/Api/Auth/Zoom`
- `php artisan test --compact tests/Unit/Services/Zoom`
- `vendor/bin/pint --dirty`

Exit criteria:

- All targeted Zoom checks pass.
- The known critical edge cases are covered by automated tests.
- The team has a clear go or no-go signal for production rollout.

## Recommended Implementation Order Inside The First PRs

1. Raw-body webhook verification plus tests.
2. Webhook payload whitelist mapping plus missing-meeting and duplicate-event handling.
3. Nullable started and ended event handling plus notification guard rails.
4. Token refresh locking plus concurrency-oriented tests.
5. Typed Saloon service contract cleanup plus transport tests.
6. Observability and release-gate cleanup.

## Notes For Future Third-Party Integrations

- Keep provider payloads out of Eloquent write paths until they are normalized.
- Do not let request classes depend on controller auth state.
- Test the transport layer directly even when service fakes exist.
- Treat webhooks as unordered, retry-prone, and partially stale by default.
- Treat token refresh as a concurrency problem, not just an authentication problem.
