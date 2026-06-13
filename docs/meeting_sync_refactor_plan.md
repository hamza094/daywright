# Plan: Polish Meeting Sync Lifecycle + Refactor MeetingService Readability

## Purpose

Improve the current Zoom meeting production-safety implementation and make `MeetingService` easier to read.

The current `MeetingService` already introduced sync lifecycle statuses:

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

This is the right direction. The next step is to polish edge cases and reduce service complexity.

Do not rewrite the whole Zoom integration. Keep the current Laravel + Saloon architecture.

---

## Current Architecture

Current flow:

```text
MeetingsController
→ MeetingStoreRequest / MeetingUpdateRequest
→ MeetingService
→ ZoomService
→ Saloon Zoom Request
→ Zoom DTO / operation result
→ Meeting model
→ MeetingResource
```

Important files to inspect first:

```text
app/Services/Project/MeetingService.php
app/Enums/Meeting/MeetingSyncStatus.php
app/Models/Meeting.php
app/Services/Zoom/ZoomService.php
app/Http/Integrations/Zoom/Requests/CreateMeeting.php
app/Http/Integrations/Zoom/Requests/UpdateMeeting.php
app/Http/Integrations/Zoom/Requests/DeleteMeeting.php
app/Http/Requests/Api/V1/Zoom/MeetingUpdateRequest.php
app/Http/Resources/Api/V1/Zoom/MeetingResource.php
database/migrations/*meetings*
```

---

## Main Goals

1. Fix stale model return after meeting creation.
2. Clear old `sync_error` when starting update/delete.
3. Make delete more production-safe.
4. Treat Zoom delete 404 as idempotent success if applicable.
5. Sanitize stored sync errors.
6. Improve readability by extracting action classes from `MeetingService`.
7. Keep `MeetingService` as a facade/orchestrator, not a giant class.

---

# Part 1: Functional Fixes

## 1. Clear Old Sync Error When Starting Update

When marking a meeting as `updating`, clear any previous sync error.

Change from:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Updating,
]);
```

To:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Updating,
    'sync_error' => null,
]);
```

---

## 2. Clear Old Sync Error When Starting Delete

When marking a meeting as `deleting`, clear any previous sync error.

Change from:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Deleting,
]);
```

To:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Deleting,
    'sync_error' => null,
]);
```

---

## 3. Improve Create Failure Accuracy

Current behavior may mark a meeting as `failed` even if Zoom creation succeeded but local DB update failed.

Recommended options:

### Option A: Keep current status simple

Use:

```text
failed
```

for any create failure.

This is acceptable for now.

### Option B: More accurate status

Add:

```text
sync_failed
```

or:

```text
create_sync_failed
```

Use it when:

```text
Zoom created the meeting successfully, but local DB sync failed.
```

Recommended for production accuracy:

```php
case SyncFailed = 'sync_failed';
```

Then use:

```text
Zoom create failed before meeting exists externally
→ failed

Zoom create succeeded but local DB update failed
→ sync_failed
```

Only implement this if it does not create too much migration/API complexity.

---

## 4. Make Delete Behavior Safer

Current flow:

```text
mark deleting
→ call Zoom delete
→ mark deleted
→ delete local record
```

This is good, but only useful if deleted records are still recoverable.

Recommended:

### If `Meeting` uses SoftDeletes

Keep:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Deleted,
    'sync_error' => null,
    'synced_at' => now(),
]);

$lockedMeeting->delete();
```

### If `Meeting` does not use SoftDeletes

Do not hard delete immediately.

Instead:

```php
$lockedMeeting->update([
    'sync_status' => MeetingSyncStatus::Deleted,
    'sync_error' => null,
    'synced_at' => now(),
]);
```

Then normal index should exclude deleted meetings because it only shows `active/synced`.

Preferred production option:

```text
Add SoftDeletes to Meeting if consistent with the rest of the project.
```

---

## 5. Treat Zoom 404 Delete As Success

Delete should be idempotent.

If the desired state is:

```text
meeting no longer exists in Zoom
```

and Zoom returns 404, that may already mean the desired state is achieved.

Implement this only if the existing Zoom exception mapping allows identifying Zoom not-found errors.

Possible flow:

```php
try {
    $zoom->deleteMeeting($currentMeeting->meeting_id, $user);
} catch (NotFoundException $exception) {
    // Treat as success for delete.
}
```

Important:

Do not treat 404 as success for create/update. Only delete.

If current exception class is too generic, add a small helper method in delete action:

```php
private function isZoomNotFound(Throwable $exception): bool
{
    return $exception instanceof NotFoundException;
}
```

Use the actual project exception namespace.

---

## 6. Sanitize Sync Error

Current `safeSyncError()` stores `$exception->getMessage()`.

Improve it so it does not store raw provider body, tokens, secrets, or long internal details.

Preferred version:

```php
private function safeSyncError(Throwable $exception): string
{
    $message = method_exists($exception, 'publicMessage')
        ? $exception->publicMessage()
        : 'Meeting sync failed. Please try again.';

    return mb_strlen($message) > 1000
        ? mb_substr($message, 0, 1000).'...'
        : $message;
}
```

If custom exceptions implement a shared interface, use that instead of `method_exists`.

Example future interface:

```php
interface HasPublicMessage
{
    public function publicMessage(): string;
}
```

Do not expose raw Zoom response bodies to normal users.

---

# Part 2: Refactor for Readability

## Current Problem

`MeetingService` is becoming too large. It currently handles:

```text
index query
show loading
create meeting flow
update meeting flow
delete meeting flow
cache locks
DB transactions
subscription limit check
sync error formatting
meeting row locking
```

This makes the service harder to scan and test.

## Recommended Refactor

Yes, create separate action classes.

Use actions for write operations:

```text
app/Actions/Meetings/CreateProjectMeeting.php
app/Actions/Meetings/UpdateProjectMeeting.php
app/Actions/Meetings/DeleteProjectMeeting.php
```

Optional shared helper/service:

```text
app/Services/Project/MeetingOperationLock.php
app/Services/Project/MeetingSyncErrorFormatter.php
```

Keep `MeetingService` as a thin facade for controller compatibility.

---

## Target Structure

```text
MeetingService
→ getMeetingsData()
→ loadForResponse()
→ createMeetingForProject() delegates to CreateProjectMeeting
→ updateProjectMeeting() delegates to UpdateProjectMeeting
→ deleteProjectMeeting() delegates to DeleteProjectMeeting
```

Action classes own the complex flows.

---

## CreateProjectMeeting Action

Path:

```text
app/Actions/Meetings/CreateProjectMeeting.php
```

Responsibility:

```text
- lock meeting creation per user
- check subscription limit
- create local pending meeting
- call Zoom create
- mark active on success
- mark failed/sync_failed on failure
- return refreshed Meeting
```

Method shape:

```php
final class CreateProjectMeeting
{
    public function handle(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        // existing create flow moved here
    }
}
```

Dependencies:

```text
PlanLimitService
MeetingOperationLock or Cache lock directly
MeetingSyncErrorFormatter
```

---

## UpdateProjectMeeting Action

Path:

```text
app/Actions/Meetings/UpdateProjectMeeting.php
```

Responsibility:

```text
- lock meeting
- find current meeting
- mark updating and clear sync_error
- call Zoom update
- apply local changes on success
- mark update_failed on failure
- return updated Meeting
```

Method shape:

```php
final class UpdateProjectMeeting
{
    public function handle(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        // existing update flow moved here
    }
}
```

Important:

meeting_id is Zoom's external meeting ID, not the local meetings table primary key. The client should not send it. The backend should use the stored Zoom meeting ID from the route-bound local Meeting model.

Use:
$currentMeeting->meeting_id

or preferably:
$currentMeeting->zoom_meeting_id

## also update the update meeting request to validate meeting_id

## DeleteProjectMeeting Action

Path:

```text
app/Actions/Meetings/DeleteProjectMeeting.php
```

Responsibility:

```text
- lock meeting
- find current meeting
- mark deleting and clear sync_error
- call Zoom delete
- treat Zoom 404 as success if applicable
- mark deleted
- soft delete or keep deleted status
- mark delete_failed on failure
```

Method shape:

```php
final class DeleteProjectMeeting
{
    public function handle(Meeting $meeting, User $user, Zoom $zoom): void
    {
        // existing delete flow moved here
    }
}
```

---

# Part 3: Shared Helpers

## MeetingOperationLock

Optional but recommended.

Path:

```text
app/Services/Project/MeetingOperationLock.php
```

Purpose:

Move repeated `Cache::lock()` logic out of `MeetingService`.

Example:

```php
final class MeetingOperationLock
{
    public function block(string $key, string $conflictMessage, Closure $callback): mixed
    {
        try {
            return Cache::lock($key, 120)
                ->block(10, fn () => $callback());
        } catch (LockTimeoutException $exception) {
            throw new ConflictHttpException($conflictMessage, $exception);
        }
    }
}
```

Then actions use:

```php
$this->locks->block(
    key: "meeting:{$meeting->getKey()}",
    conflictMessage: 'This meeting is currently being updated. Please retry.',
    callback: fn () => ...
);
```

---

## MeetingSyncErrorFormatter

Optional but useful.

Path:

```text
app/Services/Project/MeetingSyncErrorFormatter.php
```

Example:

```php
final class MeetingSyncErrorFormatter
{
    public function format(Throwable $exception): string
    {
        $message = method_exists($exception, 'publicMessage')
            ? $exception->publicMessage()
            : 'Meeting sync failed. Please try again.';

        return mb_strlen($message) > 1000
            ? mb_substr($message, 0, 1000).'...'
            : $message;
    }
}
```

---

## Meeting Row Locking

If multiple actions need `lockMeeting()`, avoid duplicating it everywhere.

Options:

### Option A: Keep private method duplicated in actions

Acceptable for now if simple.

### Option B: Create repository

```text
app/Repositories/MeetingRepository.php
```

Methods:

```php
public function findOrFail(Meeting $meeting): Meeting;
public function lockForUpdate(Meeting $meeting): Meeting;
```

Use only if the duplication becomes annoying.

Do not overengineer too early.

---

# Part 4: Update MeetingService After Refactor

Final `MeetingService` should look like this conceptually:

```php
final class MeetingService
{
    public function __construct(
        private readonly CreateProjectMeeting $createProjectMeeting,
        private readonly UpdateProjectMeeting $updateProjectMeeting,
        private readonly DeleteProjectMeeting $deleteProjectMeeting,
    ) {}

    public function getMeetingsData(Project $project, bool $isPrevious, int $perPage = 3, ?int $page = null): LengthAwarePaginator
    {
        return $project->meetings()
            ->with(['project', 'user'])
            ->synced()
            ->when($isPrevious, fn ($query) => $query->previous(), fn ($query) => $query->scheduled())
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createMeetingForProject(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->loadForResponse(
            $this->createProjectMeeting->handle($project, $user, $validated, $zoom)
        );
    }

    public function updateProjectMeeting(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        return $this->loadForResponse(
            $this->updateProjectMeeting->handle($meeting, $user, $validated, $zoom)
        );
    }

    public function deleteProjectMeeting(Meeting $meeting, User $user, Zoom $zoom): void
    {
        $this->deleteProjectMeeting->handle($meeting, $user, $zoom);
    }

    public function loadForResponse(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing(['project', 'user']);
    }
}
```

# Part 7: Tests To Add / Update

## Create

```text
- creates local pending meeting before Zoom call
- marks active and clears sync_error after Zoom success
- returns refreshed model with Zoom fields
- marks failed when Zoom create fails
- increments sync_attempts on failure
- normal index excludes pending/failed meetings
```

## Update

```text
- marks updating and clears old sync_error
- applies local changes only after Zoom succeeds
- marks update_failed and increments attempts when Zoom fails
```

## Delete

```text
- marks deleting and clears old sync_error
- marks deleted after Zoom delete success
- soft deletes or keeps deleted status depending model behavior
- marks delete_failed and increments attempts when Zoom delete fails
- treats Zoom not found as success if implemented
```

## Security

```text
- meeting must belong to project
- unauthorized user cannot update/delete
```

---

# Part 8: Anti-Overengineering Rules

Do not do these in this change unless explicitly needed:

```text
- Do not introduce queues yet.
- Do not introduce a generic OAuth manager.
- Do not rewrite Saloon connector/request classes.
- Do not create a full admin retry UI.
- Do not create a complex state machine package.
```

This refactor should stay focused:

```text
better lifecycle consistency
cleaner action classes
safer delete/update/create behavior
```

---

# Suggested Prompt For Windsurf

Use this in a new Windsurf chat:

```text
Read PLAN.md first.

I have a Laravel project with an existing Saloon-based Zoom meeting integration. The MeetingService was updated to use sync lifecycle statuses, but the class is getting messy and needs production-safety polish.

Do not rewrite the Zoom integration from scratch.

First inspect:
- app/Services/Project/MeetingService.php
- app/Enums/Meeting/MeetingSyncStatus.php
- app/Models/Meeting.php
- app/Services/Zoom/ZoomService.php
- app/Http/Requests/Api/V1/Zoom/MeetingUpdateRequest.php
- meeting migration(s)
- meeting routes

Then implement the plan in small steps:
1. Clear sync_error when marking updating/deleting.
2. Improve delete behavior with soft delete/status handling.
3. Refactor MeetingService by extracting CreateProjectMeeting, UpdateProjectMeeting, and DeleteProjectMeeting action classes.
4. Add/update tests for create/update/delete sync statuses.

Keep MeetingService as a thin facade. Keep ZoomService responsible for Zoom API calls. Keep Saloon request classes as they are.
```
