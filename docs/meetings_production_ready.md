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

## Phase 5: Tests

- Replace existing happy-path token tests with meeting-scoped tests:
  - token call uses local `meeting.id`, not Zoom `meeting_id`, in the API URL.
  - SDK config still uses Zoom `meeting.meeting_id`.
  - join button works when member object has same `id`/`uuid` but different object reference.
  - update payload does not include `meeting_id`.
  - create payload sends UTC `start_time` and user timezone.

- Keep existing `CreateZoomJwtAction` unit test, but add an integration-level assertion that backend computes role correctly.
- Run:
  - `php artisan test tests/Feature/Api/V1/Users/UserZoomTokenTest.php`
  - new meeting token feature test file
  - meeting create/update tests
  - Zoom connector tests
