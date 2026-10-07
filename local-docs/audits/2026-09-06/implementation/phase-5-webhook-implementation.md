# Phase 5: Webhook Implementation Guide

This document explains the new code added in Phase 5 for reliable Zoom webhook handling with operation fencing and staleness detection.

## Overview

Phase 5 adds timestamp-based staleness detection to prevent stale webhooks from overwriting newer operations, and allows webhooks to finalize pending update/delete operations.

## Files Changed

### 1. Database Migration (NEW FILE)

**File:** `database/migrations/2026_10_05_000001_add_last_zoom_event_timestamp_to_meetings_table.php`

**Purpose:** Add a watermark field to track the most recent Zoom event timestamp.

**Code:**

```php
$table->unsignedBigInteger('last_zoom_event_timestamp')->nullable()->after('sync_available_at');
```

---

### 2. Meeting Model

**File:** `app/Models/Meeting.php`

**Change:** Added cast for the new timestamp field.

**Code:**

```php
'last_zoom_event_timestamp' => 'integer',
```

---

### 3. ZoomWebhookSupport

**File:** `app/Services/Webhooks/ZoomWebhookSupport.php`

**Purpose:** Shared helper methods for webhook handlers.

**New Method 1: lockMeeting()**

```php
public function lockMeeting(Meeting $meeting): Meeting
{
    return Meeting::query()
        ->whereKey($meeting->getKey())
        ->lockForUpdate()  // Row lock for race prevention
        ->firstOrFail();
}
```

- Uses `SELECT ... FOR UPDATE` to lock the meeting row
- Prevents race conditions between webhook and recovery job
- Lock is held until transaction commits

**New Method 2: isStaleProviderEvent()**

```php
public function isStaleProviderEvent(Meeting $meeting, ?int $occurredAt): bool
{
    if ($occurredAt === null) {
        return true;  // Reject webhooks without timestamp
    }

    // Check against last processed webhook
    if ($meeting->last_zoom_event_timestamp !== null
        && $occurredAt <= $meeting->last_zoom_event_timestamp) {
        return true;
    }

    // Check against local sync timestamps
    $latestLocalSync = collect([$meeting->sync_started_at, $meeting->synced_at])
        ->filter(fn (mixed $timestamp): bool => $timestamp instanceof CarbonInterface)
        ->max(fn (CarbonInterface $timestamp): int => (int) $timestamp->valueOf());

    return $latestLocalSync !== null && $occurredAt <= $latestLocalSync;
}
```

- Returns `true` if webhook is stale (older than previous events or local operations)
- Compares against 3 timestamps:
  1. `last_zoom_event_timestamp` - last webhook processed
  2. `sync_started_at` - when local operation started
  3. `synced_at` - when local operation completed
- Rejects webhooks without timestamps

---

### 4. HandleMeetingUpdatedWebhook

**File:** `app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php`

**Change 1: Added Parameter**

```php
public function handle(MeetingUpdatedWebhookData $data, ?int $occurredAt = null): void
```

- `$occurredAt` is Zoom's `event_ts` from the webhook

**Change 2: Wrapped in Transaction with Locking**

```php
DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt): void {
    $lockedMeeting = $this->support->lockMeeting($meeting);

    if ($this->support->isStaleProviderEvent($lockedMeeting, $occurredAt)) {
        $this->support->logger->logWebhookIgnored(..., 'stale_provider_event', ...);
        return;
    }

    // NEW: Pending update handling
    if ($this->isPendingUpdate($lockedMeeting)) {
        $payload = $this->matchingOperationPayload($lockedMeeting, $data->changes);

        if ($payload === null) {
            $this->support->logger->logWebhookIgnored(..., 'operation_mismatch', ...);
            return;
        }

        $lockedMeeting->update($payload + [
            'sync_status' => MeetingSyncStatus::Active,
            'sync_operation_id' => null,
            'sync_operation_type' => null,
            'sync_payload' => null,
            'sync_error' => null,
            'sync_claim_token' => null,
            'sync_lease_expires_at' => null,
            'sync_available_at' => null,
            'last_zoom_event_timestamp' => $occurredAt,
            'synced_at' => now(),
        ]);
        return;
    }

    // Existing logic for normal updates...
});
```

**New Helper: isPendingUpdate()**

```php
private function isPendingUpdate(Meeting $meeting): bool
{
    return in_array($meeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
        && $meeting->sync_operation_type === MeetingSyncOperationType::Update
        && $meeting->sync_operation_id !== null;
}
```

- Returns `true` if meeting is currently being updated
- Includes `UpdateFailed` state (webhook can complete failed operations)

**New Helper: matchingOperationPayload()**

```php
private function matchingOperationPayload(Meeting $meeting, array $webhookChanges): ?array
{
    // 1. Validate payload is non-empty string
    if (! is_string($meeting->sync_payload) || trim($meeting->sync_payload) === '') {
        return null;
    }

    // 2. Decode JSON
    try {
        $payload = json_decode($meeting->sync_payload, true);
    } catch (JsonException) {
        return null;
    }

    // 3. Validate decoded value is non-empty array
    if (! is_array($payload) || $payload === []) {
        return null;
    }

    // 4. Compare each requested field with webhook data
    foreach ($payload as $field => $expectedValue) {
        if (! is_string($field)
            || ! in_array($field, self::UPDATE_FIELDS, true)
            || ! array_key_exists($field, $webhookChanges)
            || ! $this->sameValue($field, $expectedValue, $webhookChanges[$field])) {
            return null;  // Mismatch or missing field
        }
    }

    return $payload;  // All fields match
}
```

- Validates and decrypts `sync_payload`
- Compares each requested field with webhook data
- Returns payload only if ALL requested fields match webhook
- Uses `sameValue()` for value normalization

**New Helper: sameValue()**

```php
private function sameValue(string $field, mixed $expected, mixed $actual): bool
{
    if ($field === 'start_time') {
        $expectedTime = $this->normalizedTime($expected);
        return $expectedTime !== null && $expectedTime === $this->normalizedTime($actual);
    }

    if ($field === 'duration') {
        return is_numeric($expected) && is_numeric($actual) && (int) $expected === (int) $actual;
    }

    if ($field === 'join_before_host') {
        $expectedBoolean = filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $expectedBoolean !== null
            && $expectedBoolean === filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    return is_string($expected) && is_string($actual) && $expected === $actual;
}
```

- Normalizes values for comparison:
  - `start_time`: Parses to UTC datetime
  - `duration`: Casts to integer
  - `join_before_host`: Converts to boolean
  - Other fields: String comparison

**New Helper: normalizedTime()**

```php
private function normalizedTime(mixed $value): ?string
{
    if (! is_string($value) || $value === '') {
        return null;
    }

    try {
        return Carbon::parse($value)->utc()->toDateTimeString();
    } catch (Throwable) {
        return null;
    }
}
```

- Parses timestamp to UTC for consistent comparison

---

### 5. HandleMeetingDeletedWebhook

**File:** `app/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhook.php`

**Change 1: Added Parameter**

```php
public function handle(MeetingDeletedWebhookData $data, ?int $occurredAt = null): void
```

**Change 2: Wrapped in Transaction with Locking**

```php
DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt): void {
    $lockedMeeting = $this->support->lockMeeting($meeting);

    if ($lockedMeeting->sync_status === MeetingSyncStatus::Deleted) {
        $this->support->logger->logWebhookIgnored(..., 'already_deleted', ...);
        return;
    }

    if ($this->support->isStaleProviderEvent($lockedMeeting, $occurredAt)) {
        $this->support->logger->logWebhookIgnored(..., 'stale_provider_event', ...);
        return;
    }

    // NEW: Check for pending delete OR pending update
    $isPendingDelete = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Deleting, MeetingSyncStatus::DeleteFailed], true)
        && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Delete
        && $lockedMeeting->sync_operation_id !== null;

    $isPendingUpdate = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
        && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Update
        && $lockedMeeting->sync_operation_id !== null;

    // Allow delete if pending delete, pending update, or Active
    if (! $isPendingDelete && ! $isPendingUpdate && $lockedMeeting->sync_status !== MeetingSyncStatus::Active) {
        $this->support->logger->logWebhookIgnored(..., 'inactive_sync_status', ...);
        return;
    }

    $lockedMeeting->update([
        'sync_status' => MeetingSyncStatus::Deleted,
        'sync_operation_id' => null,
        'sync_operation_type' => null,
        'sync_payload' => null,
        'sync_error' => null,
        'sync_claim_token' => null,
        'sync_lease_expires_at' => null,
        'sync_available_at' => null,
        'last_zoom_event_timestamp' => $occurredAt,
        'synced_at' => now(),
    ]);
});
```

**Key Change:** Delete webhook now overrides pending update operations

- If meeting is `Updating` or `UpdateFailed` with an update operation, delete webhook will:
  - Mark meeting as `Deleted`
  - Clear all operation metadata
  - Recovery's operation ID check will reject the old update

---

### 6. HandlePersistedZoomWebhookAction

**File:** `app/Actions/Webhooks/Zoom/HandlePersistedZoomWebhookAction.php`

**Change:** Pass `provider_occurred_at` to handlers

```php
'meeting.updated' => $this->handleMeetingUpdated->handle(
    MeetingUpdatedWebhookData::fromArray($webhookInbox->payload),
    $webhookInbox->provider_occurred_at,  // ADDED
),

'meeting.deleted' => $this->handleMeetingDeleted->handle(
    MeetingDeletedWebhookData::fromArray($webhookInbox->payload),
    $webhookInbox->provider_occurred_at,  // ADDED
),
```

---

### 7. ZoomWebhookController

**File:** `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`

**Change:** Read `event_ts` from root level

```php
occurredAt: $request->input('event_ts'),
```

- All webhook endpoints read `event_ts` from the root level
- The value is passed to the inbox service as `provider_occurred_at`
- Update webhooks require this field; delete webhooks make it optional

---

### 8. Webhook Request Validators

**File:** `app/Http/Requests/Api/V1/Zoom/MeetingUpdatedWebhookRequest.php`

**Change:** Require `event_ts`

```php
'event_ts' => ['required', 'integer'],
```

**File:** `app/Http/Requests/Api/V1/Zoom/MeetingDeletedWebhookRequest.php`

**Change:** Optional `event_ts` with minimum value

```php
'event_ts' => ['sometimes', 'integer', 'min:1'],
```

- Update webhooks require a timestamp (required for staleness detection)
- Delete webhooks make the timestamp optional (null timestamps are handled as stale in handlers)
- When present, the timestamp must be a positive integer (milliseconds)

---

### 9. Tests

**Files Updated:**

- `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhookIdempotencyTest.php`
- `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhookIdempotencyTest.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`
- `tests/Unit/Models/MeetingStateMachineTest.php`

**New Test Scenarios:**

1. Matching update webhook finalizes pending update
2. Mismatching webhook cannot overwrite pending operation
3. Stale webhook cannot overwrite newer operation
4. Out-of-order events are rejected
5. Delete webhook overrides pending update
6. Delete webhook overrides failed update
7. Stale delete webhook cannot override pending update
8. Update webhook requires `event_ts`

---

## Flow Summary

```
Zoom Webhook → Controller → Inbox → Queue Job → Action → Handler
                                                      ↓
                                              lockMeeting()
                                                      ↓
                                          isStaleProviderEvent()
                                                      ↓
                            ┌─────────────────────────┴─────────────────────────┐
                            ↓                                                   ↓
                    Pending Update?                                      Pending Delete?
                            ↓                                                   ↓
              matchingOperationPayload()                            Override pending update
                            ↓                                                   ↓
                    Finalize operation                                 Finalize delete
```

---

## Key Concepts

### 1. Timestamp Watermark

- `last_zoom_event_timestamp` stores the most recent Zoom event processed
- Used to detect out-of-order webhooks
- Updated on every successful webhook processing

### 2. Staleness Detection

- Compares incoming `occurredAt` against:
  - Last processed webhook timestamp
  - Local operation start time
  - Local operation completion time
- Rejects webhooks older than any of these

### 3. Row Locking

- `SELECT ... FOR UPDATE` prevents race conditions
- Webhook and recovery job cannot write simultaneously
- Lock held until transaction commits

### 4. Operation Finalization

- Webhook can complete pending operations if:
  - All requested fields match webhook data (update)
  - Timestamp is valid (not stale)
- Clears all operation metadata on finalization
- Recovery's operation ID check prevents double-finalization

### 5. Delete Overrides Update

- If meeting is stuck in `Updating` and Zoom deletes it:
  - Delete webhook overrides the pending update
  - Marks meeting as `Deleted`
  - Recovery will reject the old update (operation ID no longer matches)

---

## Production Checklist

Before deploying to production:

1. **Verify Zoom's `event_ts` unit**
   - Capture real Zoom meeting update/delete event from sandbox
   - Confirm unit is milliseconds (not seconds)
   - Incorrect unit will break staleness detection

2. **Test against MySQL**
   - SQLite tests don't prove row-lock behavior
   - Verify `lockForUpdate()` works with MySQL

3. **Add monitoring**
   - Alert on ignored webhooks (to detect timestamp issues)
   - Alert on stuck operations in `Updating`/`Deleting` states

4. **Clock synchronization**
   - Ensure application clock is synchronized with Zoom's clock
   - Clock drift can cause legitimate webhooks to be rejected

---

## Test Results

```
✅ 30 tests, 99 assertions passed (webhook tests)
✅ 112 tests, 326 assertions total
```

/////

## Recommended Review Order

Start with the foundational concepts, then move to the specific handlers.

---

### **Step 1: Understand the Core Infrastructure**

**File:** `app/Services/Webhooks/ZoomWebhookSupport.php`

**Review these 2 methods first:**

```php
public function lockMeeting(Meeting $meeting): Meeting
{
    return Meeting::query()
        ->whereKey($meeting->getKey())
        ->lockForUpdate()  // ← KEY: Row lock prevents race conditions
        ->firstOrFail();
}
```

**What to understand:**

- This locks the database row so no other process can modify it
- Prevents webhook and recovery from writing simultaneously
- Lock is held until the transaction commits

---

```php
public function isStaleProviderEvent(Meeting $meeting, ?int $occurredAt): bool
{
    if ($occurredAt === null) {
        return true;  // ← Reject webhooks without timestamp
    }

    // Check against last processed webhook
    if ($meeting->last_zoom_event_timestamp !== null
        && $occurredAt <= $meeting->last_zoom_event_timestamp) {
        return true;  // ← Reject if older than previous webhook
    }

    // Check against local sync timestamps
    $latestLocalSync = collect([$meeting->sync_started_at, $meeting->synced_at])
        ->filter(fn (mixed $timestamp): bool => $timestamp instanceof CarbonInterface)
        ->max(fn (CarbonInterface $timestamp): int => (int) $timestamp->valueOf());

    return $latestLocalSync !== null && $occurredAt <= $latestLocalSync;  // ← Reject if older than local operation
}
```

**What to understand:**

- Compares incoming webhook timestamp against 3 reference points
- Returns `true` if webhook is stale (old)
- This is the core "staleness detection" logic

---

### **Step 2: Understand Update Webhook (More Complex)**

**File:** `app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php`

**Review the main `handle()` method:**

```php
public function handle(MeetingUpdatedWebhookData $data, ?int $occurredAt = null): void
{
    $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data, $occurredAt): void {
        DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt): void {
            $lockedMeeting = $this->support->lockMeeting($meeting);  // ← Lock row

            if ($this->support->isStaleProviderEvent($lockedMeeting, $occurredAt)) {  // ← Check staleness
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);
                return;
            }

            // ← NEW: Handle pending update
            if ($this->isPendingUpdate($lockedMeeting)) {
                $payload = $this->matchingOperationPayload($lockedMeeting, $data->changes);

                if ($payload === null) {
                    $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'operation_mismatch', $userUuid);
                    return;
                }

                $lockedMeeting->update($payload + [
                    'sync_status' => MeetingSyncStatus::Active,
                    'sync_operation_id' => null,
                    'sync_operation_type' => null,
                    'sync_payload' => null,
                    'sync_error' => null,
                    'sync_claim_token' => null,
                    'sync_lease_expires_at' => null,
                    'sync_available_at' => null,
                    'last_zoom_event_timestamp' => $occurredAt,
                    'synced_at' => now(),
                ]);
                $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
                return;
            }

            // ← Existing logic for normal updates (no pending operation)
            if (! $this->support->ensureActiveSyncStatus(self::OPERATION, $lockedMeeting, $data->meetingId, $data->requestId, $userUuid)) {
                return;
            }

            if (! $this->isMeetingUpdated($lockedMeeting, $data->changes)) {
                $lockedMeeting->update(['last_zoom_event_timestamp' => $occurredAt]);
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'no_changes', $userUuid);
                return;
            }

            $lockedMeeting->update($data->changes + ['last_zoom_event_timestamp' => $occurredAt]);
            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
        });
    });
}
```

**What to understand:**

- Everything is wrapped in `DB::transaction()`
- Row is locked first
- Staleness is checked
- **New branch:** If there's a pending update, it tries to match the payload
- **Old branch:** If no pending operation, applies webhook changes normally

---

**Then review the payload matching logic:**

```php
private function matchingOperationPayload(Meeting $meeting, array $webhookChanges): ?array
{
    // 1. Validate payload is non-empty string
    if (! is_string($meeting->sync_payload) || trim($meeting->sync_payload) === '') {
        return null;
    }

    // 2. Decode JSON
    try {
        $payload = json_decode($meeting->sync_payload, true);
    } catch (JsonException) {
        return null;
    }

    // 3. Validate decoded value is non-empty array
    if (! is_array($payload) || $payload === []) {
        return null;
    }

    // 4. Compare each requested field with webhook data
    foreach ($payload as $field => $expectedValue) {
        if (! is_string($field)
            || ! in_array($field, self::UPDATE_FIELDS, true)
            || ! array_key_exists($field, $webhookChanges)
            || ! $this->sameValue($field, $expectedValue, $webhookChanges[$field])) {
            return null;  // ← Reject if any field doesn't match
        }
    }

    return $payload;  // ← All fields match, safe to finalize
}
```

**What to understand:**

- This ensures the webhook data matches what was originally requested
- Only finalizes if ALL requested fields match
- Uses `sameValue()` for value normalization (timestamps, booleans, etc.)

---

### **Step 3: Understand Delete Webhook (Simpler)**

**File:** `app/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhook.php`

**Review the main `handle()` method:**

```php
public function handle(MeetingDeletedWebhookData $data, ?int $occurredAt = null): void
{
    $this->support->executeWithLogging(self::OPERATION, $data->meetingId, $data->requestId, function (Meeting $meeting, ?string $userUuid) use ($data, $occurredAt): void {
        DB::transaction(function () use ($meeting, $userUuid, $data, $occurredAt): void {
            $lockedMeeting = $this->support->lockMeeting($meeting);

            if ($lockedMeeting->sync_status === MeetingSyncStatus::Deleted) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'already_deleted', $userUuid);
                return;
            }

            if ($this->support->isStaleProviderEvent($lockedMeeting, $occurredAt)) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'stale_provider_event', $userUuid);
                return;
            }

            // ← NEW: Check for pending delete OR pending update
            $isPendingDelete = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Deleting, MeetingSyncStatus::DeleteFailed], true)
                && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Delete
                && $lockedMeeting->sync_operation_id !== null;

            $isPendingUpdate = in_array($lockedMeeting->sync_status, [MeetingSyncStatus::Updating, MeetingSyncStatus::UpdateFailed], true)
                && $lockedMeeting->sync_operation_type === MeetingSyncOperationType::Update
                && $lockedMeeting->sync_operation_id !== null;

            // Allow delete if pending delete, pending update, or Active
            if (! $isPendingDelete && ! $isPendingUpdate && $lockedMeeting->sync_status !== MeetingSyncStatus::Active) {
                $this->support->logger->logWebhookIgnored(self::OPERATION, $data->meetingId, $data->requestId, 'inactive_sync_status', $userUuid);
                return;
            }

            $lockedMeeting->update([
                'sync_status' => MeetingSyncStatus::Deleted,
                'sync_operation_id' => null,
                'sync_operation_type' => null,
                'sync_payload' => null,
                'sync_error' => null,
                'sync_claim_token' => null,
                'sync_lease_expires_at' => null,
                'sync_available_at' => null,
                'last_zoom_event_timestamp' => $occurredAt,
                'synced_at' => now(),
            ]);

            $this->support->logger->logWebhookProcessed(self::OPERATION, $data->meetingId, $data->requestId, $userUuid);
        });
    });
}
```

**What to understand:**

- Same pattern: transaction → lock → staleness check
- **Key difference:** Delete can override pending update (`$isPendingUpdate`)
- This handles the edge case where Zoom deletes a meeting that's stuck in `Updating`

---

### **Step 4: Review Tests (See Scenarios)**

**File:** `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhookIdempotencyTest.php`

**Look at these test methods to understand the scenarios:**

```php
public function test_matching_update_webhook_finalizes_the_current_operation()
```

- Tests that a webhook matching the pending payload finalizes the operation

```php
public function test_mismatching_or_partial_update_webhook_cannot_overwrite_pending_operation()
```

- Tests that a webhook with different data is rejected

```php
public function test_stale_update_webhook_cannot_overwrite_a_completed_newer_operation()
```

- Tests that old webhooks are rejected based on timestamp

---

**File:** `tests/Feature/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhookIdempotencyTest.php`

```php
public function test_delete_webhook_overrides_pending_update_operation()
```

- Tests that delete can override a stuck update

```php
public function test_stale_delete_webhook_cannot_override_pending_update()
```

- Tests that stale delete webhooks are rejected

---

## Summary: Review Order

1. **ZoomWebhookSupport.php** - `lockMeeting()` and `isStaleProviderEvent()` (infrastructure)
2. **HandleMeetingUpdatedWebhook.php** - `handle()` method (main logic)
3. **HandleMeetingUpdatedWebhook.php** - `matchingOperationPayload()` (payload comparison)
4. **HandleMeetingDeletedWebhook.php** - `handle()` method (delete with override)
5. **Test files** - See the actual scenarios being tested

**Start with Step 1 (ZoomWebhookSupport).** Once you understand locking and staleness detection, the handler logic will make more sense.
