# Zoom Production Readiness Plan

## Summary

Implement this as small SWE 1.6-friendly phases. Each phase should be a separate prompt/PR-sized change with focused tests. Do not rewrite the integration; fix the concrete production risks found in review: operation locks, webhook replay, token lifecycle, quota pollution, response parsing, and test reliability.

## Phase 0: Repo Hygiene And Baseline

- Remove committed temp Excel files under `storage/framework/laravel-excel/*.xls`.
- Ensure `.gitignore` excludes `storage/framework/laravel-excel/*`.
- Run the existing Zoom/meeting test subset before changes to capture baseline failures:
  - `tests/Feature/Api/V1/Meetings`
  - `tests/Feature/Api/Webhooks/Zoom`
  - `tests/Feature/Api/Middleware/Zoom`
  - `tests/Unit/Services/Zoom`
  - `tests/Unit/Http/Integrations/Zoom`

## Phase 1: Make Meeting Operation Locks Real

- Update `CreateProjectMeeting` so `MeetingOperationLock::block()` wraps the full critical section:
  - plan-limit check
  - pending meeting creation
  - Zoom create call
  - success/failure DB transition
- Update `UpdateProjectMeeting` so the same meeting lock wraps:
  - fresh meeting fetch
  - `updating` transition
  - Zoom update call
  - final active/update-failed transition
- Update `DeleteProjectMeeting` so the same meeting lock wraps:
  - fresh meeting fetch
  - `deleting` transition
  - Zoom delete call
  - final deleted/delete-failed transition
- Keep existing DB `lockForUpdate()` helper for row-level write safety.
- Do not change public controller routes or request payloads.

Tests:

- Add focused tests that prove the lock callback encloses the Zoom call by using a fake lock service that records callback boundaries.
- Add feature tests for duplicate create/update/delete behavior with same idempotency key and assert exact Zoom fake call counts.
- Strengthen `ZoomServiceFake` with `assertMeetingCreateCount()`, `assertMeetingUpdateCount()`, and `assertMeetingDeleteCount()`.

## Phase 2: Fix Created Meeting Quota Pollution

- Change created-meetings usage counting so failed/pending/deleted records do not consume the plan limit.
- Recommended implementation: update `PlanUsageCountResolver` for `CreatedMeetings` to count only meetings with `sync_status = active`.
- Keep failed rows for audit and troubleshooting.
- Do not move Zoom create before local pending row creation in this phase.

Tests:

- Add a plan-limit test where a failed Zoom create does not block a later successful create.
- Add a deleted-meeting quota test if deleted meetings currently count toward limits.
- Update existing subscription usage expectations if they assume all meeting rows count.

## Phase 3: Add Webhook Replay Protection Beyond Request ID

- Keep current Zoom signature verification unchanged.
- After signature and timestamp validation, compute a replay key from signed material:
  - `sha256(x-zm-signature + ':' + x-zm-request-timestamp + ':' + raw_body)`
- Store this key in cache for at least `TIMESTAMP_TOLERANCE_SECONDS`.
- If the replay key already exists, return a successful “accepted” style response without dispatching a job.
- Continue mapping `x-zm-request-id` to the idempotency header for existing duplicate delivery handling.
- Do not rely on `x-zm-request-id` as the only replay guard.

Tests:

- Valid signature + same timestamp/body + same request id dispatches once.
- Valid signature + same timestamp/body + different request id dispatches once total.
- Valid signature + changed body but reused request id still fails signature validation.
- Expired timestamp still returns forbidden.

## Phase 4: Harden OAuth Refresh And Unauthorized Mapping

- Map Zoom `401 Unauthorized` to `UnauthorizedException` in `ZoomConnector`.
- Keep `403 Forbidden` mapped to `UnauthorizedException`.
- In `ZoomConnectorManager::refreshAndSave()`:
  - clear tokens when refresh fails with `UnauthorizedException`
  - also clear tokens when refresh response represents OAuth `invalid_grant` or `invalid_token`
- Preserve sanitized public responses: no access token, refresh token, auth code, or raw Zoom body in API output/logs.
- Keep successful refresh locking behavior unchanged.

Tests:

- Connector maps `401` and `403` to `UnauthorizedException`.
- Expired-token refresh returning `401` clears the OAuth connection and returns reconnect-required.
- Expired-token refresh returning `400 invalid_grant` clears the OAuth connection and returns reconnect-required.
- Valid token reuse still avoids refresh.
- Existing refresh lock test should assert exactly one refresh request, not merely that a refresh was sent.

## Phase 5: Make Zoom Create Response Parsing Match Reality

- Replace synthetic-only create response assumptions with a fixture shaped like Zoom’s real create-meeting response.
- Update `Meeting::fromResponse()` to support `join_before_host` from the correct location:
  - first top-level `join_before_host` if present
  - otherwise `settings.join_before_host` if present
- Keep strict validation for fields needed to safely persist the local meeting:
  - `id`, `topic`, `start_time`, `join_url`, `start_url`, `duration`, `timezone`
- Treat non-critical optional strings as optional with safe defaults:
  - `agenda`, `password`, `status`
- If required fields are missing, keep throwing `ZoomExternalFailureException`.

Tests:

- Add fixture-based DTO test for real Zoom create response.
- Add test for nested `settings.join_before_host`.
- Add malformed required-field test.
- Add optional missing `agenda/password/status` test if real Zoom can omit them.

## Phase 6: Webhook State Safety

- Keep existing queued jobs and `WithoutOverlapping` per meeting.
- In `HandleMeetingUpdatedWebhook`, continue whitelisting safe fields only.
- Add guard so update webhooks cannot apply to deleted/deleting/non-active meetings.
- Keep start/ended idempotent status transitions.
- Do not send duplicate notifications for already-started or already-ended meetings.

Tests:

- Replay-safe update webhook does not clobber a deleted/deleting meeting.
- Duplicate start webhook sends no second notification.
- Duplicate ended webhook sends no second notification.
- Meeting update webhook ignores unsafe fields and preserves `user_id`, `project_id`, and `sync_status`.

## Phase 7: Final Verification And Cleanup

- Run targeted test suites from Phase 0.
- Run the full backend test suite if feasible.
- Search for token leakage risks:
  - no logs of access tokens
  - no logs of refresh tokens
  - no raw Zoom auth code in structured context
- Confirm no committed generated/temp files remain.
- Update or remove duplicate tests:
  - merge overlapping meeting create success/status tests
  - merge overlapping update success/synced-at tests
  - merge overlapping delete success/clear-error tests

## Public Interfaces

- No route changes.
- No request payload changes.
- No API response shape changes except safer `429`/reconnect handling where currently broken.
- Internal behavior changes:
  - meeting locks cover full operations
  - replay cache key added for Zoom webhooks
  - created-meeting quota counts active synced meetings only
  - OAuth `401`/invalid refresh failures force reconnect

## Assumptions

- The goal is production safety over preserving every existing failed-row quota behavior.
- Failed meeting rows are useful audit records but should not consume plan quota.
- Returning success for duplicate webhook replay is preferred over surfacing an error to Zoom.
- SWE 1.6 should implement one phase per prompt/branch chunk, starting with Phase 1 because it fixes the highest duplicate-meeting risk.
