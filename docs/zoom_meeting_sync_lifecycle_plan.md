# Plan: Make Zoom Meeting Create/Update/Delete More Production-Safe

## Purpose

Refactor the existing Zoom meeting integration so external Zoom operations and local database state are safer when one side succeeds and the other side fails.

The app already has a Laravel + Saloon Zoom integration. Do not rebuild the integration from scratch. Improve the existing meeting create/update/delete flow by adding a local meeting lifecycle/status model and safe failure handling.

## Current Architecture Summary

The current flow is roughly:

```text
MeetingsController
→ MeetingStoreRequest / MeetingUpdateRequest
→ MeetingService
→ ZoomService
→ Saloon Zoom Request
→ Zoom DTO / operation result
→ local Meeting model update/delete/create
→ MeetingResource
```

Important current files to inspect first:

```text
app/Http/Controllers/Api/V1/Project/MeetingsController.php
app/Http/Requests/Api/V1/Zoom/MeetingStoreRequest.php
app/Http/Requests/Api/V1/Zoom/MeetingUpdateRequest.php
app/Services/Project/MeetingService.php
app/Services/Zoom/ZoomService.php
app/Http/Integrations/Zoom/Requests/CreateMeeting.php
app/Http/Integrations/Zoom/Requests/UpdateMeeting.php
app/Http/Integrations/Zoom/Requests/DeleteMeeting.php
app/DataTransferObjects/Zoom/Meeting.php
app/DataTransferObjects/Zoom/MeetingOperationResult.php
app/Http/Resources/Api/V1/Zoom/MeetingResource.php
app/Models/Meeting.php
database/migrations/*meetings*
```

## Problem

Current create/update/delete operations call Zoom and local database separately. There is no real atomic transaction across MySQL and Zoom.

Risk examples:

```text
Create:
Zoom meeting succeeds
→ local DB insert fails
→ orphan Zoom meeting exists

Update:
Zoom update succeeds
→ local DB update fails
→ Zoom and app DB are out of sync

Delete:
Zoom delete succeeds
→ local DB delete fails
→ app still shows a meeting that no longer exists in Zoom
```

Logging alone is not enough. We want a more production-safe local state model.

## Target Design

Use local database state as the source of truth for user-facing meeting lifecycle.

Add meeting sync/lifecycle statuses.

Recommended statuses:

```text
pending
active
failed
updating
update_failed
deleting
delete_failed
deleted
```

For current implementation, keep the flow synchronous. Do not queue Zoom calls yet unless existing project architecture already supports it cleanly.

The API should only show active/scheduled meetings in normal index results. Pending/failed/deleting states should be hidden from normal user listing unless an endpoint or filter explicitly needs them.

## Phase 1: Database Changes

Add columns to the `meetings` table.

Suggested migration fields:

```php
$table->string('sync_status')->default('active')->index();
$table->text('sync_error')->nullable();
$table->timestamp('synced_at')->nullable();
$table->unsignedInteger('sync_attempts')->default(0);
```

Optional, only if needed for future queued updates:

```php
$table->json('pending_changes')->nullable();
```

Do not add `pending_changes` unless update retry needs it now.

## Phase 2: Meeting Status Enum

Create an enum:

```text
app/Enums/Meeting/MeetingSyncStatus.php
```

Example values:

```php
enum MeetingSyncStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Updating = 'updating';
    case UpdateFailed = 'update_failed';
    case Deleting = 'deleting';
    case DeleteFailed = 'delete_failed';
    case Deleted = 'deleted';
}
```

Update `Meeting` model casts:

```php
'sync_status' => MeetingSyncStatus::class,
'synced_at' => 'datetime',
```

## Phase 3: Create Flow

Current create flow likely does:

```text
check subscription limit
→ call Zoom create
→ create local meeting
```

Change to:

```text
check subscription limit
→ create local meeting as pending
→ call Zoom create
→ if Zoom succeeds:
      update local meeting with Zoom response
      sync_status = active
      sync_error = null
      synced_at = now()
→ if Zoom fails:
      sync_status = failed
      sync_error = safe exception message
      sync_attempts += 1
      rethrow controlled exception or return failed response depending existing API style
```

Important rules:

- The pending local record must be created before the Zoom API call.
- Keep the existing subscription limit lock.
- Keep existing operation lock.
- Do not create duplicate meetings if request is retried too quickly.
- If Zoom creation fails, keep the failed local record for admin/debug/retry visibility.
- Normal index should not show failed/pending meetings unless explicitly requested.

Pseudo-shape:

```php
$projectMeeting = DB::transaction(function () use ($project, $lockedUser, $validated) {
    return $project->meetings()->create([
        ...$validated,
        'user_id' => $lockedUser->id,
        'sync_status' => MeetingSyncStatus::Pending,
    ]);
});

try {
    $zoomMeeting = $zoom->createMeeting($validated, $lockedUser);

    DB::transaction(function () use ($projectMeeting, $zoomMeeting) {
        $lockedMeeting = $this->lockMeeting($projectMeeting);

        $lockedMeeting->update([
            ...(array) $zoomMeeting,
            'sync_status' => MeetingSyncStatus::Active,
            'sync_error' => null,
            'synced_at' => now(),
        ]);
    });
} catch (Throwable $exception) {
    DB::transaction(function () use ($projectMeeting, $exception) {
        $lockedMeeting = $this->lockMeeting($projectMeeting);

        $lockedMeeting->update([
            'sync_status' => MeetingSyncStatus::Failed,
            'sync_error' => $this->safeSyncError($exception),
            'sync_attempts' => DB::raw('sync_attempts + 1'),
        ]);
    });

    throw $exception;
}
```

Adapt names and style to existing codebase.

## Phase 4: Update Flow

Current update flow likely does:

```text
call Zoom update
→ update local DB
```

More production-safe synchronous flow:

```text
lock meeting
→ mark local meeting as updating
→ call Zoom update
→ if Zoom succeeds:
      apply local changes
      sync_status = active
      sync_error = null
      synced_at = now()
→ if Zoom fails:
      sync_status = update_failed
      sync_error = safe exception message
      sync_attempts += 1
      keep previous stable data where possible
```

Important rule:

Do not require `meeting_id` in `MeetingUpdateRequest`. The route-bound `Meeting` model should be the source of truth. The client should not send `meeting_id`.

Change validation:

Remove:

```php
'meeting_id' => 'integer|required',
```

Then in `MeetingService`, pass the real Zoom meeting ID from the current local meeting:

```php
$zoom->updateMeeting($localAttributes + [
    'meeting_id' => $currentMeeting->meeting_id,
], $user);
```

For update, prefer keeping old stable data if Zoom fails. Do not apply local changes if Zoom update failed.

## Phase 5: Delete Flow

Current delete flow likely does:

```text
call Zoom delete
→ delete local DB
```

Change to:

```text
lock meeting
→ mark local meeting as deleting
→ call Zoom delete
→ if Zoom succeeds:
      mark sync_status = deleted
      then soft delete if model uses SoftDeletes
      or hard delete only if existing app expects hard delete
→ if Zoom fails:
      sync_status = delete_failed
      sync_error = safe exception message
      sync_attempts += 1
      keep record for retry/admin visibility
```

Preferred production behavior:

- If `Meeting` model already uses SoftDeletes, use soft delete.
- If not, consider adding SoftDeletes only if consistent with project conventions.
- Avoid hard deleting before Zoom delete succeeds.
- A `delete_failed` meeting should not appear in normal index unless admin/debug view.

## Phase 6: Index / Show Query Behavior

Update meeting listing so normal user index only shows user-facing valid meetings.

Current query likely uses scopes like:

```php
previous()
scheduled()
```

Add filtering:

```php
where('sync_status', MeetingSyncStatus::Active)
```

or create a model scope:

```php
public function scopeSynced($query)
{
    return $query->where('sync_status', MeetingSyncStatus::Active);
}
```

Then use:

```php
$project->meetings()
    ->synced()
    ->with(...)
```

For `show`, decide:

- If user opens a failed/deleting meeting, return 404 or controlled error.
- Recommended: normal show should only expose active meetings.

## Phase 7: MeetingResource

Expose `sync_status` only if useful for frontend.

For normal meeting response:

```php
'sync_status' => $this->sync_status?->value,
```

Optional:

Expose `sync_error` only to owner/admin, not all project members.

## Phase 8: Retry Path

Do not build a full admin panel unless already present.

But add a clean internal method that makes retry possible later:

```text
retryFailedMeetingCreation()
retryFailedMeetingUpdate()
retryFailedMeetingDelete()
```

For now, it is acceptable to add only TODOs or private methods if not used.

If implementing retry now, keep it small:

- Retry create only for `failed`.
- Retry update only for `update_failed`.
- Retry delete only for `delete_failed`.

## Phase 9: Tests

Add or update tests for:

### Create

```text
- creates pending local meeting before Zoom call
- marks meeting active when Zoom create succeeds
- marks meeting failed when Zoom create fails
- active meetings appear in index
- failed/pending meetings do not appear in normal index
- subscription limit is still enforced
```

### Update

```text
- removes client-provided meeting_id requirement
- uses local meeting_id for Zoom update
- marks active/synced after successful Zoom update
- marks update_failed if Zoom update fails
- does not apply local changes when Zoom update fails
```

### Delete

```text
- marks meeting deleting before Zoom delete
- marks deleted / soft deletes after Zoom delete succeeds
- marks delete_failed if Zoom delete fails
- delete_failed meeting does not appear in normal index
```

### Security / correctness

```text
- meeting must belong to project
- unauthorized users cannot update/delete
```

## Important Implementation Rules

1. Do not rewrite the whole Zoom integration.
2. Keep Saloon request classes.
3. Keep ZoomService responsible for Zoom API calls.
4. Keep MeetingService responsible for business orchestration.
5. Do not introduce queues yet unless required.
6. Do not create generic OAuth abstraction in this change.
7. Prefer small focused changes.
8. Keep API response format consistent with existing ApiController responses.
9. Do not log secrets, tokens, Zoom start_url, or refresh tokens.
10. Store safe error messages only. Avoid exposing raw provider responses to users.

## Expected End State

After this change:

```text
Create:
local pending record exists before Zoom call
successful Zoom call marks active
failed Zoom call marks failed

Update:
Zoom success applies local update
Zoom failure marks update_failed and keeps previous stable data

Delete:
local meeting marked deleting first
Zoom success marks deleted / soft deletes
Zoom failure marks delete_failed

Index:
normal meeting list only shows active/synced meetings
```
