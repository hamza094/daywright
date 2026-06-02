# Zoom Integration Phase 0 Baseline

Created: 2026-06-02

This document implements Phase 0 of the Zoom hardening plan.
It freezes the current Zoom integration surface, captures the current response and behavior contracts, records the known schema leak points, and distinguishes compatibility constraints from later hardening targets.

Use this document as the reference point for every later Zoom hardening change.
If a later phase changes behavior listed here, that change should be intentional, tested, and documented.

## Goal Of This Baseline

- Freeze the currently supported Zoom API and webhook surface.
- Record the contracts that must not regress accidentally.
- Record the known technical debt that later phases are expected to change.
- Distinguish between currently supported behavior and desired production-safe behavior.

## Frozen Supported Surface

### Project Meeting API

Current supported meeting endpoints:

- `GET /api/v1/projects/{project}/meetings`
- `GET /api/v1/projects/{project}/meetings/{meeting}`
- `POST /api/v1/projects/{project}/meetings`
- `PATCH /api/v1/projects/{project}/meetings/{meeting}`
- `DELETE /api/v1/projects/{project}/meetings/{meeting}`

Current behavioral notes:

- Meeting routes are nested under project routes and use scoped bindings.
- Meeting create and update are protected by user-scope idempotency middleware.
- The index supports `request=previous` as the current alias for historical meetings.
- Meeting reads and writes currently rely on the `Zoom` contract, usually backed by `ZoomService` in runtime and `ZoomServiceFake` in feature tests.

### User Zoom Token API

Current supported token endpoints:

- `GET /api/v1/users/me/zoom-token`
- `GET /api/v1/users/me/zoom-jwt-token`

Current behavioral notes:

- `zoom-token` returns a wrapped ZAK token payload.
- `zoom-jwt-token` returns a wrapped SDK JWT token payload.
- The ZAK token path is feature-tested with a fake Zoom provider and unit-tested through the real Saloon-backed service.

### OAuth API

Current supported OAuth endpoints:

- `GET /api/v1/oauth/zoom/redirect`
- `GET /api/v1/oauth/zoom/callback`

Current behavioral notes:

- Redirect returns a wrapped `redirect_url` and caches the PKCE verifier by OAuth state.
- Callback expects `code` and `state`, retrieves the cached verifier, exchanges tokens, and persists Zoom OAuth details on the authenticated user.
- Callback currently treats missing required fields as a `400 Bad Request`.
- Callback currently treats `access_denied` as a `400 Bad Request`.

### Webhook API

Current supported webhook endpoints:

- `POST /api/v1/webhooks/zoom/meetings/update`
- `POST /api/v1/webhooks/zoom/meetings/delete`
- `POST /api/v1/webhooks/zoom/meetings/start`
- `POST /api/v1/webhooks/zoom/meetings/ended`

Current behavioral notes:

- The route group applies `VerifyZoomWebhook` and global-scope idempotency middleware.
- The controller acknowledges supported events with a message response and dispatches background jobs.
- The current implementation assumes only these four Zoom meeting event types are supported.
- No endpoint validation handshake implementation was found in the current codebase.

## Frozen Supported Zoom Webhook Events

Supported now:

- `meeting.updated`
- `meeting.deleted`
- `meeting.started`
- `meeting.ended`

Not implemented or not frozen as supported:

- Any non-meeting Zoom webhook event family
- Any additional meeting event beyond the four route-specific handlers above
- Zoom endpoint validation flow

## Current Response Contract Baseline

### Meetings

Current response shapes:

- Meeting index: paginated resource response with `data`, `links`, and `meta`
- Meeting show: wrapped single resource under `data`
- Meeting create: wrapped single resource under `data`
- Meeting update: wrapped single resource under `data`
- Meeting delete: message-only response with `message = "Meeting deleted successfully."`

Current error expectations proven by tests:

- Validation failures return `422`
- User-correctable Zoom failures return `400`
- Update and delete rollback behavior is preserved when Zoom operations fail

### User Zoom Tokens

Current response shapes:

- ZAK token endpoint: `{ "data": { "zak_token": "..." } }`
- JWT token endpoint: `{ "data": { "jwt_token": "..." } }`

### OAuth

Current response shapes:

- Redirect: `{ "data": { "redirect_url": "..." } }`
- Callback success: `{ "message": "Zoom account connected successfully" }`

Current callback error expectations proven by tests:

- Missing required fields: `400` with `message = "Missing required fields"`
- Zoom user error: `400` with `code = "zoom_error"`
- Zoom upstream failure: `503` with `code = "zoom_unavailable"`

### Webhooks

Current response shapes:

- Supported webhook acknowledgment: `{ "message": "Webhook accepted." }`
- Missing required request id header: `400` with `message = "Missing required Zoom webhook header: x-zm-request-id."`
- Invalid signature or stale timestamp: `403` with `message = "The webhook signature was invalid."`

## Current Schema Leak Points

These are frozen as known debt and should be changed only by later hardening phases.

### Raw Body Verification Leak

Current state:

- `VerifyZoomWebhook` signs a JSON string rebuilt from `request()->all()` instead of the exact raw request body.

Risk:

- Signature verification depends on parsed input shape instead of the original Zoom payload bytes.
- This is a real hardening target and is not treated as a compatibility requirement.

### Provider Payload To Local Model Leak

Current state:

- `ZoomWebhookController::update()` forwards `collect($object)->except(['id', 'uuid'])->toArray()` into `UpdateMeetingWebhook`.
- `UpdateMeetingWebhook` updates the meeting model with the forwarded payload.

Risk:

- Provider payload keys can flow too directly into local model writes.
- This is a hardening target and not a behavior that must be preserved.

### Provider Transport Return Type Leak

Current state:

- The `Zoom` interface returns a DTO for create, but mixed return types for update and delete.
- `ZoomService` returns raw Saloon responses for update and delete.

Risk:

- The service contract is harder to reason about and harder to reuse consistently.
- This is a hardening target and not a compatibility requirement for external clients.

## Current Test Boundary Baseline

### Feature Tests That Use `ZoomServiceFake`

The following tests currently replace the runtime Zoom implementation with `ZoomServiceFake` through `Tests\Traits\InteractsWithZoom`:

- `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php`
- `tests/Feature/Api/V1/Users/UserZoomTokenTest.php`
- `tests/Feature/Api/Auth/Zoom/ZoomOAuthRedirectTest.php`
- `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`
- `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`

What these tests prove well:

- Controller and route contracts
- Persistence and rollback behavior around meeting writes
- OAuth cache and user-update behavior
- Response envelopes and status codes

What these tests do not prove:

- Saloon endpoint resolution
- Saloon request body composition
- Provider authentication and refresh behavior at the transport layer
- Connector exception mapping under real mocked HTTP responses

### Current Direct Transport Coverage

Current direct provider-transport coverage found during Phase 0:

- `tests/Unit/Services/Zoom/ZoomZakTokenTest.php`

Implication:

- Phase 0 confirms that the current suite is strong on route and service contracts but still thin on direct Saloon request coverage.

## Compatibility Decisions Frozen In Phase 0

These current behaviors are treated as compatibility constraints until a later phase intentionally changes them:

- Keep the current meeting CRUD route shape under `/projects/{project}/meetings`.
- Keep the current OAuth route shape under `/oauth/zoom/redirect` and `/oauth/zoom/callback`.
- Keep the current user token route shape under `/users/me/zoom-token` and `/users/me/zoom-jwt-token`.
- Keep the current meeting index compatibility alias `request=previous`.
- Keep the current webhook acknowledgment message `Webhook accepted.` unless a later phase documents a contract change.
- Keep the current OAuth redirect shape `data.redirect_url`.
- Keep the current OAuth callback success message contract.
- Keep the current meeting delete message contract.

## Hardening Targets Explicitly Not Frozen As Compatibility Behavior

These are current behaviors, but they are not protected as compatibility requirements:

- Rebuilding webhook signatures from parsed input
- Passing provider update payloads directly into local write paths
- Returning mixed transport response types from the `Zoom` service contract
- Depending on request auth context inside Saloon request classes
- Missing endpoint validation support for Zoom webhooks
- Treating all missing-meeting webhook scenarios as hard failures

## Phase 0 Exit Status

Phase 0 is complete when read as a baseline documentation phase because:

- The supported Zoom surface is explicit.
- The currently supported webhook events are explicit.
- The current external response contracts are frozen.
- The known schema leak points are recorded.
- The current fake-versus-transport test boundary is recorded.
- Compatibility behavior is separated from later hardening targets.

## Follow-On Work Unblocked By This Baseline

- Phase 1 can now harden the webhook HTTP boundary without accidentally changing the frozen public route and response surface.
- Phase 2 can now replace provider payload pass-through behavior with normalized internal payloads.
- Phase 3 can now harden token lifecycle behavior with clear current OAuth expectations.
- Phase 4 can now tighten the Saloon contract and test strategy against a frozen external API surface.
