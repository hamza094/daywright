# Production-Ready Zoom Meeting Token Alignment Plan

## Summary

Replace the unsafe user-scoped Zoom token flow with a meeting-scoped flow. The backend will authorize the current user against the project meeting, compute the Zoom SDK role server-side, issue ZAK only for the meeting owner starting the meeting, and return one safe token payload to the frontend. Also fix the frontend/backend meeting update mismatch, member join visibility, timezone metadata, and the confirmed token refresh bug.

Assumption chosen: use the production-ready strategy now, not backwards compatibility. The old `/users/me/zoom-token` and `/users/me/zoom-jwt-token` endpoints are frontend-only and excluded from docs, so replace their usage instead of preserving unsafe behavior.

## Phase 1: Secure Token Issuance

- Add a meeting-scoped endpoint:
  - `POST /projects/{project}/meetings/{meeting}/zoom-tokens`
  - Request body: `{ "action": "start" | "join" }`
  - Response: `{ "jwt_token": "...", "zak_token": "..." | null }`
- Backend authorization rules:
  - Route uses scoped bindings so the `Meeting` must belong to the `Project`.
  - Current user must have `access` to the project.
  - `start` requires current user to be the meeting owner/project owner.
  - `join` allows project owner or active project members.
  - Reject inactive/unsynced meetings before issuing tokens.
- Backend token rules:
  - Compute SDK role on the server: owner/start = `1`, join/member = `0`.
  - Never accept `role` from the client.
  - Use the meeting’s stored Zoom `meeting_id` as the SDK meeting number.
  - Fetch ZAK only for `start`; return `zak_token: null` for `join`.
- Remove frontend usage of:
  - `GET /users/me/zoom-token`
  - `GET /users/me/zoom-jwt-token`
- Delete or stop routing the old token endpoints and remove/update `JwtTokenRequest`.

## Phase 2: Update Meetings Frontend

- Change `fetchTokens` to call the new endpoint with the local app meeting ID and action:
  - `POST /projects/{projectSlug}/meetings/{meeting.id}/zoom-tokens`
  - Body includes `{ action }`.
- Stop sending client-computed `role`.
- Keep SDK join config as-is:
  - `meetingNumber: meeting.meeting_id`
  - `signature: jwt_token`
  - `zak: zak_token` only for start.
- Pass toast handling consistently into `setupAndJoinMeeting`, or remove its internal toast branch and let `initializeMeeting` handle errors in one place.
- Fix `shouldShowJoinButton` to compare IDs/UUIDs instead of object identity:
  - owner cannot show Join
  - active project member with same `id` or `uuid` can show Join.
- Keep Echo/status subscription using local `meeting.id`; that part is already correct.

## Phase 3: Fix Meeting Form Alignment

- In `ViewModal`, remove `meeting_id` from editable form state and update payload.
  - `meeting_id` remains display-only.
  - `MeetingUpdateRequest` continues to prohibit `meeting_id`.
- Initialize edit form from the selected meeting when entering edit mode, not partly in `getMeeting` and partly in `initializeUpdateMeeting`.
- For meeting creation, set submitted `timezone` to the user/browser display timezone instead of hardcoded `UTC`.
  - Keep `start_time` submitted as UTC ISO.
  - Store/return `start_time` in UTC.
  - Store meeting timezone as the intended display/Zoom timezone.

## Phase 4: Fix Token Refresh Recovery

- In `ZoomConnectorManager::isInvalidOAuthError`, replace `$exception->getContext()` with `$exception->context()`.
- Keep the existing reconnect-required public message/code behavior:
  - message: `Zoom account connection needs to be re-authorized.`
  - code: `zoom_reconnect_required`
- Add coverage for invalid OAuth refresh context mapping to reconnect-required.

## Phase 5: Webhook Reliability Baseline

Fix VerifyZoomWebhook ordering:Verify signature/timestamp first.
Handle endpoint.url_validation before replay-cache logic.
Keep replay protection for real event webhooks.

Confirm production config requirements:QUEUE_CONNECTION must not be sync in production.
queue worker/Horizon/Supervisor must run continuously.
CACHE_STORE should be shared Redis/database, not local array/file, because replay protection and WithoutOverlapping()->shared() need shared state.

Fix existing webhook tests:clear replay/idempotency cache between tests;
update start_time assertions now that Meeting::start_time is cast to datetime;
keep webhook test suite green before proceeding.

Add missing webhook tests:invalid signature returns 403;
stale timestamp returns 403;
missing webhook secret returns 500;
repeated endpoint validation still returns plainToken/encryptedToken;
terminal job failure logs zoom_webhook_failed.

## Phase 6: Webhook Job Recovery And Observability

Keep queued webhook jobs async; Zoom should receive a fast 200.
Treat queued job failure as internal recovery responsibility:retain tries, backoff, failed() logging, and WithoutOverlapping;
alert/monitor on zoom_webhook_failed;
document/operator path for retrying failed jobs.

Ensure logs stay safe:no access tokens, refresh tokens, ZAK, start URLs, passwords, or webhook secrets;
include only safe IDs: meeting_id, request_id, operation, user UUID.

Add tests for:failed job logs sanitized context;
duplicate start/end webhooks do not duplicate notifications;
ended meeting cannot be restarted;
inactive sync_status webhooks are ignored.

## Phase 7: Meeting Visibility And Status UX

Keep two separate meanings clear:status: Zoom runtime state, such as waiting, started, ended.
sync_status: app-to-Zoom lifecycle, such as active, update_failed, delete_failed, deleted.

User/project frontend behavior:normal members see only usable active meetings;
project owner sees active, update_failed, and delete_failed;
hide deleted from normal project UI;
show both status and sync_status badges where recovery is needed.

Current/Previous tabs:keep current split by start_time for now;
make ended visually obvious even if it appears under Current due to scheduled time;
disable Start/Join when status = ended or sync_status != active.

Admin:do not block core production work on a full admin meeting UI.
If admin meeting visibility is added, show local id, Zoom meeting_id, owner, project, status, sync_status, start_time, timezone, synced_at, and safe/truncated sync_error.

## Phase 8: Tests

- Keep existing `CreateZoomJwtAction` unit test, but add an integration-level assertion that backend computes role correctly.
- Run:
  - `php artisan test tests/Feature/Api/V1/Users/UserZoomTokenTest.php`
  - new meeting token feature test file
  - meeting create/update tests
  - Zoom connector tests
