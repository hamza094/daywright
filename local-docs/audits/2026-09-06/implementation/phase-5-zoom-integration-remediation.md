# Phase 5 Zoom Integration Remediation Plan

**Status:** Completed in the local worktree; all 11 tickets have focused coverage. Production evidence remains required before deployment.

**Completed Tickets:**

- ✅ Ticket 1 — Correct webhook ordering and timestamp ties
- ✅ Ticket 2 — Preserve all allowlisted fields when completing a pending update
- ✅ Ticket 3 — Normalize legacy persisted inbox timestamps
- ✅ Ticket 4 — Correct the Phase 5 webhook implementation guide
- ✅ Ticket 5 — Align the Zoom rate-limit exception constructor
- ✅ Ticket 6 — Restore a consistent uncertain-creation contract
- ✅ Ticket 7 — Prevent stale recovery workers from mutating Zoom
- ✅ Ticket 8 — Normalize provider HTTP 429 responses
- ✅ Ticket 9 — Make meeting DELETE safely repeatable
- ✅ Ticket 10 — Reset per-operation retry state
- ✅ Ticket 11 — Strengthen stale-callback and password-log tests

**Scope:** The 11 confirmed open implementation and deployment findings from the latest Zoom integration review. Two additional coverage gaps are included under their related tickets. The raw-storage encryption assertion is already corrected and is excluded.

## Ticket 1 — Correct webhook ordering and timestamp ties

**Status:** ✅ Completed

**Priority:** P1/P2  
**Start with:** `app/Services/Webhooks/ZoomWebhookSupport.php`, `app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php`, `app/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhook.php`, and their feature tests.

**Findings:** Completing a pending update sets `synced_at` to webhook processing time; this can cause a later provider event to be rejected. The watermark check also rejects equal timestamps, even for distinct events. A delayed provider callback can also predate a successful local API or recovery update even when no newer provider watermark has been stored.

**Implemented policy:** Keep `last_zoom_event_timestamp` as the provider-event ordering watermark. `sync_started_at` fences callbacks against the current pending attempt; an update callback must be strictly newer than that attempt to complete it, and timestamp ties stay pending for recovery reconciliation. `sync_reconcile_before_at` identifies update callbacks that may overlap a local operation; those callbacks are reconciled with Zoom's current meeting state instead of being rejected as stale. Delete events are ordered only against the provider watermark, so a local completion time cannot discard a real deletion. Existing rows seed the reconciliation cutoff from `synced_at`; this may cause an extra Zoom read, but it never rejects a provider event. `sync_started_at` is not used to backfill because it may record a rejected attempt. Event-key deduplication remains authoritative; for distinct equal-timestamp provider events, delete wins over update.

**Regression coverage:** Process a matching pending update event and a later delete event only after simulated queue delay; assert the meeting becomes `Deleted`. Process distinct update and delete events with the same normalized timestamp; assert the meeting does not remain active. Verify a rate-limited update does not block a valid delete webhook. Reconcile delayed update callbacks against Zoom after synchronous and recovery completion, including the password-plus-join-URL case. Reject an older matching update callback and an ambiguous timestamp tie without clearing the pending operation.

**Done when:** Delayed update/delete, equal-timestamp update/delete, and post-completion delayed-callback behavior pass at handler, synchronous-action, and recovery boundaries, with inbox event-key deduplication unchanged.

## Ticket 2 — Preserve all allowlisted fields when completing a pending update

**Status:** ✅ Completed

**Priority:** P2  
**Start with:** `app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php`, `app/DataTransferObjects/Zoom/MeetingUpdatedWebhookData.php`, and `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhookIdempotencyTest.php`.

**Finding:** The handler validates operation-matching values but persists only that requested payload. Other normalized provider changes, such as a changed `join_url`, are lost.

**Implemented fix:** After the operation payload matches, persist the validated operation fields together with the normalized provider changes, including `join_url`. Changes remain restricted to the DTO/handler allowlist, and completion fields cannot be overridden by webhook data.

**Regression coverage:** Finalize a password update where the event also includes a changed `join_url`; assert both are stored. Keep mismatch and allowlist tests.

**Done when:** Matching operation fields complete the operation and all permitted accompanying provider fields persist, with no unallowlisted mass assignment.

## Ticket 3 — Normalize legacy persisted inbox timestamps

**Status:** ✅ Completed

**Priority:** Deployment compatibility  
**Start with:** `app/Services/Webhooks/ZoomWebhookInboxService.php`, `app/Actions/Webhooks/Zoom/HandlePersistedZoomWebhookAction.php`, `app/Services/Webhooks/ZoomWebhookTimestamp.php`, and inbox-service tests.

**Finding:** Timestamp normalization occurs in `accept()` only when creating a new row. Existing pending rows containing seconds are read unchanged and compared with millisecond values.

**Recommended fix:** Normalize persisted Zoom event timestamps at a single processing/read boundary, or provide a safe, repeatable migration for affected pending Zoom rows. Avoid repeatedly multiplying already-normalized milliseconds. Keep null timestamps null and preserve the documented optional delete timestamp behavior.

**Regression coverage:** Create a pending inbox row with a seconds timestamp directly in storage, process it, and verify its handler receives the normalized millisecond value. Also cover already-millisecond and null values.

**Deployment requirement:** Confirm the migration/read strategy against production row volume and ensure it runs before normal processing resumes.

**Done when:** Old pending rows and newly accepted events follow the same timestamp unit contract without double conversion.

## Ticket 4 — Correct the Phase 5 webhook implementation guide

**Status:** ✅ Completed

**Priority:** Documentation  
**Start with:** `local-docs/audits/2026-09-06/implementation/phase-5-webhook-implementation.md` and the current webhook controller/request classes.

**Finding:** The guide documents a `payload.event_ts` fallback and `required_without` rules that are absent from the implementation.

**Recommended fix:** Remove obsolete examples and describe the actual request contract: update timestamps are required; delete timestamps are optional; the controller reads root `event_ts`; seconds are normalized to milliseconds; timestamp-less delete handling follows the actual handler policy. Keep examples aligned with the current DTO and validation code.

**Done when:** A line-by-line search finds no obsolete fallback/validation example and the guide matches current request, inbox, and handler behavior.

## Ticket 5 — Align the Zoom rate-limit exception constructor

**Status:** ✅ Completed

**Priority:** P1  
**Start with:** `app/Exceptions/Integrations/Zoom/ZoomRateLimitException.php`, `app/Services/Zoom/ZoomService.php`, `tests/Unit/Services/Zoom/ZoomMeetingUpdateTest.php`, and `tests/Unit/Services/Zoom/ZoomMeetingDeleteTest.php`.

**Finding:** Update and delete service paths pass `previous:` to a constructor that does not accept that named parameter, causing an `Error` before the action's rate-limit rollback can run.

**Recommended fix:** Add a nullable `Throwable` previous-exception argument and forward it to the parent exception constructor, or remove the named argument consistently if retaining exception chaining is not part of the contract. Preserve retry-after information.

**Regression coverage:** Trigger the Saloon local rate-limit path through `ZoomService` for both update and delete. Do not rely only on directly injecting `ZoomRateLimitException` into `ZoomServiceFake`.

**Done when:** The real service throws `ZoomRateLimitException` without an argument error for both operations, preserving its retry delay and previous exception as designed.

## Ticket 6 — Restore a consistent uncertain-creation contract

**Status:** ✅ Completed

**Priority:** P1  
**Start with:** `app/Services/Zoom/ZoomService.php`, `app/Actions/Meetings/CreateProjectMeeting.php`, `app/Exceptions/Integrations/Zoom/ZoomMeetingCreationUnknownException.php`, and creation service/action/API tests.

**Finding:** `ZoomService` throws `ZoomMeetingOperationUnknownException` for uncertain creation, while `CreateProjectMeeting` catches only `ZoomMeetingCreationUnknownException`. The generic exception branch can bypass durable `CreateUnknown` handling and the accepted meeting response contract.

**Implemented decision:** Preserve `ZoomMeetingCreationUnknownException`. The creation action catches that type, persists the durable unknown state, schedules recovery, and keeps the established accepted meeting response contract.

**Regression coverage:** Cause an uncertain result through the real service path, exercise `CreateProjectMeeting`, and assert `CreateUnknown`, retry scheduling, and the expected accepted meeting response. Update old service expectations accordingly.

**Done when:** The uncertain-create exception reaches the intended durable creation flow from service through API and recovery, with creation-specific service/action tests passing.

## Ticket 7 — Prevent stale recovery workers from mutating Zoom

**Status:** ✅ Completed

**Priority:** P1/P2 concurrency  
**Start with:** `app/Actions/Meetings/PerformZoomMeetingRecovery.php`, `app/Actions/Meetings/UpdateProjectMeeting.php`, `app/Actions/Meetings/DeleteProjectMeeting.php`, `app/Services/Project/MeetingOperationLock.php`, and recovery tests.

**Finding:** Recovery checks its loaded claim before remote lookup. A lease can expire and a newer operation can complete before the old worker sends its stale PATCH. The final local operation-ID check cannot undo a remote mutation.

**Implemented fix:** Coordinate recovery writes and matching local completion with the same per-meeting operation lock used by user mutations. After acquiring that lock, reload the meeting and validate operation ID, claim token, operation type, and lease immediately before mutation. Network I/O remains outside a database transaction. The 120-second lock lifetime exceeds the configured 30-second provider request timeout, preventing normal lease expiry during a provider call.

**Regression coverage:** Pause recovery after lookup, expire/replace its claim, complete a newer update, resume the old worker, and assert it sends no PATCH. Cover delete writes as well if recovery shares the path.

**Done when:** A worker with a replaced/expired claim cannot issue a remote mutation or stale local completion, while current recovery can still proceed without holding a DB transaction during network I/O. Focused tests replace claims during the update lookup and verify no provider mutation.

## Ticket 8 — Normalize provider HTTP 429 responses

**Status:** ✅ Completed

**Priority:** P1/P2  
**Start with:** `app/Http/Integrations/Zoom/ZoomConnector.php`, `app/Services/Zoom/ZoomService.php`, `app/Actions/Meetings/PerformZoomMeetingRecovery.php`, and service/recovery tests.

**Finding:** Saloon's local rate-limit exception is translated, but a Zoom HTTP 429 is mapped to `ZoomExternalFailureException`. Synchronous actions can mark the operation failed, and recovery ignores provider `Retry-After`.

**Recommended fix:** Map HTTP 429 to `ZoomRateLimitException` (or a single equivalent rate-limit contract) for reads and writes used by update/delete recovery. Parse `Retry-After` defensively, accepting only a positive supported delay; use normal backoff when missing or invalid. Ensure recovery reads do not classify 429 as a permanent client error.

**Regression coverage:** Mock an HTTP 429 with `Retry-After: 120` through the real Zoom service for update/delete and a recovery read/write. Assert rollback/retry behavior and the 120-second delay. Cover invalid, zero, and absent headers.

**Done when:** All relevant HTTP 429 responses enter the same retry contract as local rate limits, preserving valid positive delays.

## Ticket 9 — Make meeting DELETE safely repeatable

**Status:** ✅ Completed

**Priority:** P1/P2  
**Implemented in:** `app/Actions/Meetings/DeleteProjectMeeting.php`, `routes/api/v1/projects/meetings.php`, and meeting-delete feature tests.

**Finding:** The pinned idempotency middleware handles POST/PUT/PATCH, not DELETE. Applying it to this route did not enforce an idempotency key or replay a response. A repeated request could reach an action that rejected an already-deleted meeting.

**Implemented contract:** Keep the Composer patch scoped to its existing concerns. The DELETE route no longer advertises middleware that does not process DELETE. A request for an already `Deleted` meeting returns success without another Zoom call. A request while the meeting is `Deleting` returns 409 because recovery still owns an uncertain operation. The delete intent is protected by a database row lock inside its transaction.

**Regression coverage:** Repeat DELETE through the API and assert two success responses with exactly one Zoom deletion. Send DELETE while recovery is pending and assert 409 with no Zoom deletion. Cover the corresponding action behavior and row state.

**Done when:** Repeated completed deletes are safe and make one Zoom mutation; pending recovery deletes return an accurate in-progress response; the route does not imply unsupported package-level DELETE idempotency.

## Ticket 10 — Reset per-operation retry state

**Status:** ✅ Completed

**Priority:** P2  
**Start with:** `app/Actions/Meetings/UpdateProjectMeeting.php`, `app/Actions/Meetings/DeleteProjectMeeting.php`, and their feature tests.

**Finding:** New update/delete operation IDs do not reset `sync_attempts`; an operation started after earlier retries may reach the attempt limit too early. A delete intent can also retain an old update payload.

**Recommended fix:** When saving each new operation intent, reset `sync_attempts` and initialize claim token, lease, availability, error, payload, and operation fields for that operation. Delete must clear `sync_payload`; update must replace it with its new payload. Preserve the current operation identity and status transition rules.

**Regression coverage:** Run an operation through several failed recovery attempts, complete it, start a new update and a new delete, and assert each starts with a fresh attempt budget and no stale payload/claim state.

**Done when:** Each new operation begins with clean, internally consistent retry and claim state.

## Ticket 11 — Strengthen stale-callback and password-log tests

**Status:** ✅ Completed

**Priority:** Coverage  
**Start with:** `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhookIdempotencyTest.php`, `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhookIdempotencyTest.php`, and `tests/Feature/Actions/Meetings/UpdateProjectMeetingTest.php`.

**Findings:** Existing stale-event tests set up a stale timestamp before handling; they do not exercise a callback arriving late after a newer operation replaced the operation ID. The password-log test asserts database state but does not inspect logs.

**Implemented fix:** Added deterministic tests for a delayed callback after synchronous and recovery completion, without manually advancing the provider watermark. The delayed update/delete test now completes an `Updating` operation. The password test causes an operational warning and asserts the sentinel password is absent from both its message and context. Legacy timestamp coverage reloads a persisted seconds row and processes it through the inbox handler boundary.

**Done when:** The tests observe the behavior they claim to protect across the webhook, synchronous action, recovery, persisted inbox, and logging boundaries.

## Recommended implementation sequence

### SWE 1.6 ready track

Give SWE 1.6 one ticket per run in this order. Each ticket has a local, testable contract and does not require it to invent product policy.

1. **Ticket 5 — Rate-limit exception constructor.** This is a small PHP contract fix. Require the existing previous exception and retry-after behavior to remain unchanged. Verify update and delete through `ZoomService`.
2. **Ticket 8 — HTTP 429 normalization.** Keep the change limited to Zoom connector/service/recovery translation and retry-delay validation. Require tests for positive, invalid, zero, and missing `Retry-After` values.
3. **Ticket 10 — Fresh operation retry state.** Reset only operation fields when creating a new update/delete intent. Preserve status transitions, operation IDs, and existing provider calls. Test update after retries and delete after an old update payload.
4. **Ticket 2 — Pending-update field preservation.** Merge only the existing normalized allowlist after operation matching. Keep completion fields controlled by the handler. Test password plus `join_url` and an unallowlisted field.
5. **Ticket 3 — Legacy inbox timestamp normalization.** Use one idempotent normalization boundary and avoid double conversion. Test seconds, milliseconds, null, and an existing pending row.
6. **Ticket 4 — Documentation correction.** Update examples from the current request validators/controller and run a text search for obsolete `payload.event_ts` and `required_without` examples.
7. **Ticket 11 — Test strengthening.** Add deterministic test seams around the already-defined behavior. If a test requires a production synchronization mechanism, stop and report the missing seam instead of weakening the assertion.

For every SWE 1.6 run, provide this instruction:

> Start only with the files named in this ticket. Inspect current callers and existing tests before editing. Implement the recommended behavior exactly, add the listed regression tests, and preserve public response shapes, state-machine transitions, provider payloads, encryption, and unrelated frontend/OpenAPI files. Run the focused tests, `git diff --check`, and applicable formatter/static checks. Report changed files, test output, and any proof that still requires MySQL, Redis, multiple workers, or a Zoom sandbox. Stop if the ticket requires choosing a conflict policy or changing a public contract that this ticket does not specify.

## Verification and release evidence

For each ticket, run focused PHPUnit/frontend tests and the relevant static/style checks. The tests establish local behavior only. Before deployment, separately verify multi-worker locking and timestamp migration against the production database engine, then exercise Zoom update/delete, rate limiting, webhook ordering, and recovery in a provider sandbox. Record exactly which environments were used; local SQLite, mocks, and documentation are not production proof.
