# Zoom Webhook Jobs Refactoring Plan

## Summary

Refactor Laravel queued jobs for Zoom meeting webhooks to move business logic from jobs into dedicated action/handler classes. Jobs will remain thin, handling only queue concerns.

## Current State Analysis

### Jobs (contain too much business logic)

**StartMeetingWebhook.php** (199 lines)

- Constructor: extracts meeting_id, start_time, request_id
- Middleware: WithoutOverlapping
- handle(): finds meeting, checks sync_status, checks status, updates status atomically, sends notifications, logs
- failed(): logs failure
- backoff(): [5, 30]
- tags(): uses trait
- Private methods: getMeeting(), updateMeetingStatus(), sendNotifications(), shouldStartMeeting()

**MeetingEndsWebhook.php** (194 lines)

- Similar structure to StartMeetingWebhook
- Handles meeting ended logic with atomic status update

**UpdateMeetingWebhook.php** (162 lines)

- Uses MeetingWebhookUpdateData DTO for whitelisted fields
- Checks for actual changes before updating
- No notifications or events

**DeleteMeetingWebhook.php** (123 lines)

- Marks sync_status as deleted
- Soft deletes the meeting
- Uses firstOrFail (throws exception if missing)

### Supporting Infrastructure (KEEP UNCHANGED)

- **InteractsWithZoomWebhookLogging trait** - Logging methods (119 lines)
- **MeetingWebhookUpdateData DTO** - Whitelisted field normalization
- **MeetingSyncStatus enum** - With acceptsZoomRuntimeWebhook() method
- **MeetingState enum** - START, ENDS
- **Meeting model** - No SoftDeletes trait (uses sync_status = deleted pattern)
- **ZoomWebhookController** - Dispatches jobs
- **VerifyZoomWebhook middleware** - Validates webhooks

## Files to Create

```
app/Actions/Webhooks/Zoom/HandleMeetingStartedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingEndedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhook.php
```

## Files to Modify

```
app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php
app/Jobs/Webhooks/Zoom/MeetingEndsWebhook.php
app/Jobs/Webhooks/Zoom/UpdateMeetingWebhook.php
app/Jobs/Webhooks/Zoom/DeleteMeetingWebhook.php
```

## Refactoring Phases

### Phase 1: Create Action Classes (No Breaking Changes)

Create all four action classes with business logic extracted from jobs. Jobs remain unchanged initially.

**1.1 HandleMeetingStartedWebhook**

- Extract logic from StartMeetingWebhook
- Constructor accepts: meeting_id, start_time, request_id
- handle() method with:
  - Find meeting by meeting_id
  - Ignore if missing
  - Ignore if sync_status not active
  - Atomic status transition to START (ignore if already START or ENDS)
  - Dispatch MeetingStatusUpdate event
  - Send MeetingStarted notification once
  - Log processed/ignored/retry
- Use InteractsWithZoomWebhookLogging trait (or create ZoomWebhookLogger service if trait doesn't work cleanly)

**1.2 HandleMeetingEndedWebhook**

- Extract logic from MeetingEndsWebhook
- Constructor accepts: meeting_id, start_time, end_time, request_id
- handle() method with:
  - Find meeting by meeting_id
  - Ignore if missing
  - Ignore if sync_status not active
  - Atomic status transition to ENDS (ignore if already ENDS)
  - Dispatch MeetingStatusUpdate event
  - Send MeetingEnded notification once
  - Log processed/ignored/retry

**1.3 HandleMeetingUpdatedWebhook**

- Extract logic from UpdateMeetingWebhook
- Constructor accepts: meeting_id, update_data (array), request_id
- handle() method with:
  - Find meeting by meeting_id
  - Ignore if missing
  - Ignore if sync_status not active
  - Use MeetingWebhookUpdateData-normalized fields
  - Ignore if no actual changes
  - Update safe changed fields only (whitelisted)
  - Log processed/ignored/retry

**1.4 HandleMeetingDeletedWebhook**

- Extract logic from DeleteMeetingWebhook
- Constructor accepts: meeting_id, request_id
- handle() method with:
  - Find meeting by meeting_id
  - Ignore if missing (change from firstOrFail to first())
  - If already deleting/deleted, treat as already handled
  - Mark sync_status as deleted
  - Clear sync_error
  - Set synced_at
  - Soft delete if Meeting uses SoftDeletes (check model)
  - Otherwise keep row with sync_status = deleted
  - Log processed/ignored/retry

### Phase 2: Update Jobs to Use Actions (One at a Time)

**2.1 Update StartMeetingWebhook**

- Keep: constructor, middleware, tries, backoff, tags, failed()
- Remove: private methods (getMeeting, updateMeetingStatus, sendNotifications, shouldStartMeeting)
- Remove: business logic from handle()
- Add: inject HandleMeetingStartedWebhook in handle()
- New handle():
  ```php
  public function handle(HandleMeetingStartedWebhook $handler): void
  {
      $handler->handle(
          meetingId: $this->meeting_id,
          startTime: $this->start_time,
          requestId: $this->request_id,
      );
  }
  ```
- Keep InteractsWithZoomWebhookLogging trait for failed() and tags()

**2.2 Update MeetingEndsWebhook**

- Same pattern as StartMeetingWebhook
- Inject HandleMeetingEndedWebhook

**2.3 Update UpdateMeetingWebhook**

- Same pattern
- Inject HandleMeetingUpdatedWebhook

**2.4 Update DeleteMeetingWebhook**

- Same pattern
- Inject HandleMeetingDeletedWebhook

### Phase 3: Logging Decision

**Decision Point:** Determine if InteractsWithZoomWebhookLogging trait can be used in action classes

- If trait works cleanly in actions: use it directly
- If trait has job-specific dependencies (currentAttempt(), retryDelayForCurrentAttempt()):
  - Create app/Services/Webhooks/ZoomWebhookLogger.php
  - Move logging methods from trait to service
  - Update trait to use service internally
  - Actions use service directly

### Phase 4: Test Updates

Update/add tests to verify behavior remains unchanged:

**4.1 Job Tests**

- StartMeetingWebhook calls HandleMeetingStartedWebhook
- MeetingEndsWebhook calls HandleMeetingEndedWebhook
- UpdateMeetingWebhook calls HandleMeetingUpdatedWebhook
- DeleteMeetingWebhook calls HandleMeetingDeletedWebhook

**4.2 Handler Tests**

- Start handler ignores missing meeting
- Start handler ignores inactive sync_status
- Start handler sends notification only once
- Start handler does not revive ended meeting
- Start handler uses atomic status update

- Ended handler ignores missing meeting
- Ended handler ignores inactive sync_status
- Ended handler sends notification only once
- Ended handler uses atomic status update

- Update handler ignores missing meeting
- Update handler ignores inactive sync_status
- Update handler only updates whitelisted changed fields
- Update handler ignores if no actual changes

- Delete handler marks deleted safely
- Delete handler is safe for duplicate delete events
- Delete handler ignores missing meeting

### Phase 5: DTO Consideration

**Decision Point:** Evaluate if DTOs are needed for action constructors

Current job constructors accept raw arrays. Options:

- Keep raw arrays in action constructors (simple, matches current pattern)
- Create DTOs for each action (more type safety, more boilerplate)
- Use existing MeetingWebhookUpdateData for update action, raw arrays for others

Recommendation: Keep raw arrays for simplicity unless type safety issues arise.

## Important Behaviors to Preserve

### Start Webhook

- Atomic status update pattern (WHERE status != START AND status != ENDS)
- Ignore if already started or ended
- Dispatch MeetingStatusUpdate event after successful transition
- Send MeetingStarted notification once

### Ended Webhook

- Atomic status update pattern (WHERE status != ENDS)
- Ignore if already ended
- Dispatch MeetingStatusUpdate event after successful transition
- Send MeetingEnded notification once

### Update Webhook

- Use MeetingWebhookUpdateData whitelisted fields only
- Never modify: id, user_id, project_id, sync_status, sync_error, sync_attempts, synced_at, created_at, updated_at, start_url, meeting_id
- Ignore if no actual changes detected

### Delete Webhook

- Safe for duplicate delete events (check if already deleting/deleted)
- Mark sync_status = deleted
- Clear sync_error
- Set synced_at
- Soft delete if model has SoftDeletes trait
- Otherwise keep row with sync_status = deleted

## Zoom Meeting ID Rule

Zoom meeting IDs can be int64/more than 10 digits. Use `int|string` type hint consistently throughout.

## Anti-Overengineering Rules

DO NOT:

- Rewrite routes
- Rewrite VerifyZoomWebhook middleware
- Rewrite idempotency middleware
- Rewrite Saloon integration
- Introduce event sourcing
- Introduce new webhook events table
- Build admin UI

DO:

- Keep current queue behavior
- Keep current middleware
- Keep current logging behavior
- Keep current idempotency
- Make jobs thin
- Move business logic to action classes

## Implementation Order

1. Create all four action classes (Phase 1)
2. Update StartMeetingWebhook to use action (Phase 2.1)
3. Test StartMeetingWebhook
4. Update MeetingEndsWebhook to use action (Phase 2.2)
5. Test MeetingEndsWebhook
6. Update UpdateMeetingWebhook to use action (Phase 2.3)
7. Test UpdateMeetingWebhook
8. Update DeleteMeetingWebhook to use action (Phase 2.4)
9. Test DeleteMeetingWebhook
10. Resolve logging approach (Phase 3)
11. Update/add tests (Phase 4)
12. Final verification

## Notes

- Meeting model does NOT have SoftDeletes trait visible - verify before implementing delete handler
- InteractsWithZoomWebhookLogging trait uses job-specific methods (currentAttempt(), retryDelayForCurrentAttempt()) - may need service extraction
- All jobs use the same middleware pattern - preserve exactly
- All jobs use the same backoff [5, 30] - preserve exactly
- All jobs use the same tries = 3 - preserve exactly
