# Reliable Zoom Meeting Update and Delete

## Summary

Extend the existing `meetings` table state machine. Do not introduce a new generic outbox table or queue yet.

Keep update and delete synchronous:

```text
Save operation intent
→ commit database transaction
→ call Zoom
→ save success or failure
→ scheduled recovery handles interrupted operations
```

Do not change the existing meeting creation recovery flow.

## Phase 1: Add operation data ✅ COMPLETED

Update the meetings table with:

- `sync_operation_type`: `create`, `update`, or `delete` (string, nullable)
- `sync_payload`: encrypted longText containing requested update values (used encryption overhead and future-proofing)
- Reuse `sync_operation_id` as the operation ID.
- Reuse existing `sync_status`, `sync_attempts`, `sync_available_at`, `sync_claim_token`, and lease fields.

For update, save the requested values in `sync_payload`. This is necessary because the normal meeting fields must not change until Zoom confirms the update.

For delete, `sync_payload` can remain empty.

### Implementation Details:

- Created `MeetingSyncOperationType` enum with `Create`, `Update`, `Delete` cases
- Added custom `MeetingBuilder` QueryBuilder for recovery scopes (DDD compliance)
- Moved recovery scopes from model to QueryBuilder: `updatingWithExpiredLeaseAt`, `deletingWithExpiredLeaseAt`, `updateFailedDueAt`, `deleteFailedDueAt`, `readyForUpdateRecoveryAt`, `readyForDeleteRecoveryAt`, `readyForAnyRecoveryAt`
- Updated `Meeting` model with casts for `sync_operation_type` (enum) and `sync_payload` (encrypted)
- Added helper methods: `isCreateOperation()`, `isUpdateOperation()`, `isDeleteOperation()`
- Added `newEloquentBuilder()` to use custom QueryBuilder

## Phase 2: Improve update flow ✅ COMPLETED

Modify `UpdateProjectMeeting`:

1. Lock the meeting in a short transaction.
2. Allow only `active` or `update_failed` meetings.
3. Generate a new operation ID.
4. Save:
   - operation type `update`
   - operation ID
   - encrypted requested payload
   - status `updating`
   - attempt and retry fields
5. Commit the transaction.
6. Call Zoom using the saved payload (outside transaction).
7. On success, open another transaction:
   - lock the meeting
   - verify the operation ID still matches (via `executeWithOperationIdCheck` helper)
   - apply the saved payload to the local meeting
   - clear pending operation data
   - set status to `active`
8. On `ZoomMeetingUpdateUnknownException` (timeout, network, ambiguous 5xx):
   - keep status `updating` with expired lease
   - schedule recovery in 1 minute
   - background worker will reconcile with Zoom
9. On `ZoomRateLimitException` (429):
   - rollback to `active` state (clear payload, operation_id)
   - throw exception with Retry-After header
   - user retries manually (no background job)
10. On definite failure (400, 404, 401, 403):
    - keep the original meeting values
    - save the error
    - set status to `update_failed`
    - no retry scheduled

An old request must not update the meeting if a newer update operation has replaced its operation ID.

### Implementation Details:

- Created `ZoomMeetingUpdateUnknownException` for timeout/network uncertainty
- Created `ZoomRateLimitException` with `retryAfterSeconds()` method and Retry-After header
- Updated `ZoomService` to throw typed exceptions based on Saloon/HTTP outcomes
- Added `executeWithOperationIdCheck()` helper to DRY up operation ID validation
- Adaptive error handling: user-recoverable (rate limit) vs system-recoverable (timeout)
- Rate limits roll back to active for manual retry, timeouts schedule background recovery

## Phase 3: Improve delete flow

Modify `DeleteProjectMeeting`:

1. Lock the meeting in a short transaction.
2. Allow only `active`, `update_failed`, or `delete_failed` meetings.
3. Generate a new operation ID.
4. Save operation type `delete`.
5. Set status to `deleting`.
6. Commit before calling Zoom.
7. Call Zoom outside the transaction.
8. On success or Zoom `404`:
   - lock the meeting
   - verify the operation ID
   - clear operation data
   - set status to `deleted`
9. On definite failure:
   - save the error
   - set status to `delete_failed`
   - schedule a retry.
10. Keep the local row as a tombstone. Do not physically delete it while remote deletion is unresolved.

Add idempotency middleware to the delete route, matching the update route.

## Phase 4: Add recovery

Extend `RecoverAmbiguousZoomMeetings` or create a focused `RecoverPendingZoomMeetingOperations` action.

Recovery must process:

- `creating`
- `create_unknown`
- `updating`
- `update_failed` when retry is due
- `deleting`
- `delete_failed` when retry is due

Use the existing claim token and lease fields:

1. Select only due operations.
2. Atomically claim one operation.
3. Verify the claim before finalizing.
4. For update:
   - fetch the Zoom meeting
   - compare the requested payload with the remote meeting
   - mark local data active if it already matches
   - retry the update if it does not match
5. For delete:
   - fetch or delete the Zoom meeting
   - treat `404` as successful deletion
   - retry temporary failures
6. After the maximum attempts, set manual review state and clear the live claim.

Do not send a second update if Zoom already applied the first update.

Before recovery sends an update or delete to Zoom, it must also acquire the
same per-meeting operation lock used by normal user mutations. After acquiring
the lock, reload the meeting and validate the operation ID, claim token,
operation type, and unexpired lease immediately before the provider write.
Keep the provider request outside database transactions. The lock lifetime
must cover the configured provider request timeout, or be safely renewed, so
an expired recovery claim cannot overlap a newer operation while the old
worker still sends a remote mutation. If the claim or operation changed,
skip the provider write and let the recovery path reconcile the current state.

## Phase 5: Update webhook behavior

Update the Zoom webhook handlers:

- `meeting.updated` should safely apply changes while the operation is current.
- An update callback may finalize a pending operation only when its provider timestamp is strictly newer than the current attempt start and its requested fields match. Older callbacks and timestamp ties remain pending for recovery to reconcile.
- `meeting.deleted` should finalize a matching local delete operation.
- Order provider events by `last_zoom_event_timestamp`; use `sync_local_mutation_at` only for confirmed local-operation completion. Failed or rate-limited attempts must not advance that local barrier.
- Repeated webhooks must remain harmless.
- A stale webhook must not overwrite a newer local operation.
- Keep webhook inbox deduplication unchanged.

## Test coverage status

The implementation-focused regression coverage below exists. Keep these tests: they exercise different boundaries (action behavior, queued recovery, inbox persistence, and HTTP contracts), even where the same outcome is asserted at more than one layer.

### Operation data and update flow — covered

- `MeetingStateMachineTest` covers operation state and model invariants; meeting update tests cover encrypted payload persistence.
- `UpdateProjectMeetingTest` covers success, definite failure, timeout recovery state, rate-limit rollback, stale operation fencing, and rejection of overlapping updates.
- `RecoverZoomMeetingOperationJobTest` covers reconciling an already-applied update, retrying when Zoom still has old values, stale claim/operation rejection, and retry timing.

### Delete flow — covered

- `DeleteProjectMeetingTest` covers successful deletion, Zoom 404, temporary failure, timeout, rate-limit behavior, operation fencing, overlap rejection, tombstone retention, and repeated deletion.
- `RecoverZoomMeetingOperationJobTest` covers recovery when the Zoom meeting is already gone and retry scheduling for uncertain outcomes.
- `MeetingDeleteTest` covers the delete HTTP response and the Zoom 404 contract.

### Recovery claims and creation compatibility — covered

- `RecoverPendingZoomMeetingOperationsTest` covers claiming due work, skipping live claims, choosing another due operation, and reclaiming expired leases.
- `RecoverZoomMeetingOperationJobTest` and `ZoomRecoveryPolicyTest` cover recovery outcomes, retry delays, and policy limits.
- `RecoverAmbiguousZoomMeetingTest` covers the existing create recovery path, including stale claims, bounded retry, and manual review.

### Webhook and inbox behavior — covered

- `HandleMeetingUpdatedWebhookIdempotencyTest` covers matching and mismatching pending updates, late completion, duplicate processing, and stale/out-of-order events.
- `HandleMeetingDeletedWebhookIdempotencyTest` covers pending delete finalization, delete overriding pending/failed update, stale-event protection, duplicate processing, and deletion without `event_ts`.
- `ZoomWebhookInboxServiceTest`, `WebhookInboxCrashRecoveryTest`, and `WebhookInboxTest` cover inbox deduplication, claims, retry scheduling, crash recovery, and request acceptance.
- `ZoomWebhookTest` covers signed webhook acceptance and endpoint validation.

### Remaining verification gap

- Add an HTTP-level DELETE idempotency-key test if the API contract requires delete retries with the same key to replay safely. Current `MeetingDeleteTest` verifies deletion behavior, but does not verify the key/replay contract. The update and create replay contracts have separate coverage in `IdempotencyContractTest`.

## Implementation order

Implement one phase at a time:

1. Migration, model casts, and operation helpers.
2. Update flow.
3. Delete flow.
4. Recovery command/action.
5. Webhook coordination.
6. API and integration tests.
7. Run focused tests, then the full backend quality gate.

## Done when

- No update or delete operation is lost after a process crash.
- Local meeting fields change only after Zoom confirms the update.
- A Zoom `404` delete is treated as success.
- Unknown results remain recoverable.
- Retry operations are bounded and claim protected.
- Old workers and stale webhooks cannot overwrite newer operations.
- Existing meeting creation recovery tests still pass.
- Update and delete API responses remain compatible.
