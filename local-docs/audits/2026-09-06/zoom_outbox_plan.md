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

## Phase 5: Update webhook behavior

Update the Zoom webhook handlers:

- `meeting.updated` should safely apply changes while the operation is current.
- If an update webhook matches the pending update payload, it may finalize the operation.
- `meeting.deleted` should finalize a matching local delete operation.
- Repeated webhooks must remain harmless.
- A stale webhook must not overwrite a newer local operation.
- Keep webhook inbox deduplication unchanged.

## Tests to add or update

### Migration and model tests ✅ COMPLETED

- Operation type and payload are persisted.
- Update payload is encrypted.
- Payload is restored correctly through the model.
- Operation IDs are unique.
- Custom QueryBuilder is used for recovery scopes.

### Update tests ✅ COMPLETED

- Successful update saves Zoom and local values.
- Zoom failure leaves original local values unchanged.
- Timeout leaves the operation recoverable.
- Rate limit rolls back to active for manual retry.
- Operation ID prevents stale update.
- A second update is rejected while one is already active.
- Sync payload is encrypted.
- Password payload is never written to logs.

### Update tests (REMAINING - Phase 4)

- Recovery finalizes when Zoom already contains the requested values.
- Recovery retries when Zoom still has old values.
- A stale worker cannot finalize a newer update.
- Retry from `update_failed` works.

### Delete tests

Add tests for:

- Successful delete changes status to `deleted`.
- Zoom `404` is treated as success.
- Temporary Zoom failure changes status to `delete_failed`.
- Recovery retries a failed delete.
- Recovery handles an already deleted Zoom meeting.
- A stale delete worker cannot delete or finalize a newer operation.
- Repeated delete request is idempotent.
- Delete route requires and respects the idempotency key.

### Recovery command tests

Add tests for:

- Only due operations are selected.
- Future retry operations are skipped.
- An expired claim can be taken by another worker.
- A live claim cannot be taken by another worker.
- Maximum attempts move the operation to manual review.
- Update and delete recovery do not affect create recovery behavior.

### Webhook tests

Add tests for:

- Update webhook finalizes a matching update.
- Delete webhook finalizes a matching delete.
- Duplicate update webhook has no second effect.
- Duplicate delete webhook has no second effect.
- A stale webhook cannot overwrite a newer operation.

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
