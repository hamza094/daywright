# Plan: Refine Zoom Webhook Architecture and Production Safety

## Purpose

Improve the current Laravel Zoom webhook architecture so responsibilities are clear, jobs stay minimal, payloads are typed, duplicate notifications are prevented, and webhook behavior is production-safe.

The current architecture is:

```text
ZoomWebhookController
→ ZoomWebhookDispatcher
→ queued webhook Job
→ webhook Action
```

This is a good structure. Do not rewrite it from scratch.

The goal is to refine responsibility boundaries and fix the remaining edge cases.

## Main Files To Inspect First

```text
app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php
app/Http/Requests/Api/V1/Zoom/WebhookRequest.php

app/Services/Webhooks/ZoomWebhookDispatcher.php
app/Services/Webhooks/ZoomWebhookLogger.php

app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php
app/Jobs/Webhooks/Zoom/MeetingEndsWebhook.php
app/Jobs/Webhooks/Zoom/UpdateMeetingWebhook.php
app/Jobs/Webhooks/Zoom/DeleteMeetingWebhook.php
app/Jobs/Webhooks/Zoom/Concerns/InteractsWithZoomWebhookLogging.php

app/Actions/Webhooks/Zoom/HandleMeetingStartedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingEndedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingUpdatedWebhook.php
app/Actions/Webhooks/Zoom/HandleMeetingDeletedWebhook.php

app/DataTransferObjects/Zoom/MeetingWebhookUpdateData.php
app/Enums/Meeting/MeetingSyncStatus.php
app/Enums/MeetingState.php
app/Models/Meeting.php

app/Notifications/Zoom/MeetingStarted.php
app/Notifications/Zoom/MeetingEnded.php

routes/api.php
config/idempotency.php
tests covering Zoom webhooks
```

## Target Responsibility Boundaries

### Controller

The controller should only:

```text
receive validated request
extract request ID
call dispatcher
return accepted response
```

Do not place event-specific business logic in the controller.

### Dispatcher

`ZoomWebhookDispatcher` should:

```text
convert validated raw webhook payload into typed DTO
dispatch the correct job
use dispatchAfterResponse consistently
```

The dispatcher should not:

```text
query database
update meetings
send notifications
log business outcomes
```

### Job

Jobs should remain thin queue wrappers.

Keep in jobs:

```text
constructor payload
tries
backoff
WithoutOverlapping middleware
tags
failed() hook
handle() calling the action
```

Do not move queue concerns into action/service classes.

The job `handle()` should ideally look like:

```php
public function handle(HandleMeetingStartedWebhook $handler): void
{
    $handler->handle($this->payload);
}
```

### Action

Actions should own webhook business logic:

```text
find meeting
check sync lifecycle
perform atomic state transition
update safe meeting fields
send/dispatch notification
dispatch MeetingStatusUpdate event
log processed/ignored/retry
```

Do not move this logic into a generic service only to make the action shorter.

## Part 1: Fix Critical Notification Bug

### Problem

In started/ended handlers, the atomic status update method currently returns `void`.

If the update affects zero rows, it logs the webhook as ignored but the outer `handle()` continues and may still send a notification.

This can cause duplicate notifications.

### Fix

Change transition methods to return `bool`.

#### Started

```php
private function transitionToStarted(
    Meeting $meeting,
    int|string $meetingId,
    ?string $requestId,
): bool {
    $updated = Meeting::query()
        ->whereKey($meeting->getKey())
        ->where('status', '!=', MeetingState::START->value)
        ->where('status', '!=', MeetingState::ENDS->value)
        ->update([
            'status' => MeetingState::START->value,
        ]);

    if ($updated === 0) {
        $this->logger->logWebhookIgnored(
            self::OPERATION,
            $meetingId,
            $requestId,
            'already_started_or_ended',
            $this->userUuid($meeting),
        );

        return false;
    }

    $meeting->refresh();

    event(new MeetingStatusUpdate($meeting));

    return true;
}
```

Use:

```php
if (! $this->transitionToStarted($meeting, $meetingId, $requestId)) {
    return;
}

$this->sendNotifications($meeting, $startTime);
```

Apply the same pattern with `transitionToEnded()`.

## Part 2: Remove Redundant Pre-Checks

After atomic transitions become authoritative, these methods are mostly redundant:

```text
shouldStartMeeting()
shouldEndMeeting()
```

Remove them unless they provide a distinct business rule not already enforced by the atomic update.

Use the conditional database update as the final source of truth.

## Part 3: Introduce Typed Webhook DTOs

The current flow passes arrays through:

```text
Dispatcher → Job → Action
```

Replace these untyped arrays with small readonly DTOs.

Create:

```text
app/DataTransferObjects/Zoom/Webhooks/MeetingStartedWebhookData.php
app/DataTransferObjects/Zoom/Webhooks/MeetingEndedWebhookData.php
app/DataTransferObjects/Zoom/Webhooks/MeetingUpdatedWebhookData.php
app/DataTransferObjects/Zoom/Webhooks/MeetingDeletedWebhookData.php
```

Examples:

```php
final readonly class MeetingStartedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $startTime,
        public ?string $requestId,
    ) {}
}
```

```php
final readonly class MeetingEndedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $requestId,
    ) {}
}
```

```php
final readonly class MeetingUpdatedWebhookData
{
    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        public int|string $meetingId,
        public array $changes,
        public ?string $requestId,
    ) {}
}
```

```php
final readonly class MeetingDeletedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $requestId,
    ) {}
}
```

Keep DTO fields scalar/array only so queue serialization remains simple.

## Part 4: Normalize Update Payload Once

Current flow may normalize update changes in both:

```text
ZoomWebhookDispatcher
HandleMeetingUpdatedWebhook
```

Normalize and whitelist exactly once.

Recommended:

```text
raw payload object
→ MeetingWebhookUpdateData::fromPayloadObject()
→ MeetingUpdatedWebhookData DTO
→ job
→ action
```

The action should receive already normalized and whitelisted changes.

Remove duplicate calls to `MeetingWebhookUpdateData::normalizeChanges(...)` inside the action if dispatcher already did it.

## Part 5: Event-Specific Request Validation

The generic `WebhookRequest` validates only:

```php
'event' => ['required', 'string']
```

This does not guarantee that `meeting.started` was sent to `/start`.

Create separate request classes:

```text
MeetingStartedWebhookRequest
MeetingEndedWebhookRequest
MeetingUpdatedWebhookRequest
MeetingDeletedWebhookRequest
```

Each should validate the exact event value.

Example:

```php
'event' => ['required', Rule::in(['meeting.started'])]
```

Recommended event mapping:

```text
start route  → meeting.started
ended route  → meeting.ended
update route → meeting.updated
delete route → meeting.deleted
```

Also validate route-specific payload fields.

## Part 6: Extract Shared Job Configuration

The four jobs duplicate:

```text
tries
backoff
WithoutOverlapping
tags
failed()
meeting/user lookup inside failed()
```

Create an abstract base job:

```text
app/Jobs/Webhooks/Zoom/ZoomMeetingWebhookJob.php
```

Suggested shape:

```php
abstract class ZoomMeetingWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int|string $meeting_id;

    public ?string $request_id;

    abstract protected function operation(): string;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                key: "zoom-meeting:{$this->meeting_id}",
                releaseAfter: 5,
            ))
                ->shared()
                ->expireAfter(120),
        ];
    }

    public function backoff(): array
    {
        return [5, 30];
    }

    public function failed(Throwable $exception): void
    {
        $meeting = Meeting::query()
            ->where('meeting_id', $this->meeting_id)
            ->first();

        $userUuid = $meeting?->user()->value('uuid') ?: null;

        app(ZoomWebhookLogger::class)->logWebhookFailed(
            operation: $this->operation(),
            meetingId: $this->meeting_id,
            requestId: $this->request_id,
            exception: $exception,
            userIdentifier: $userUuid,
        );
    }

    public function tags(): array
    {
        return [
            'zoom',
            'webhook',
            $this->operation(),
            "zoom-meeting:{$this->meeting_id}",
        ];
    }
}
```

Each concrete job should only provide event-specific payload properties, constructor, `operation()`, and `handle()`.

Do not use inheritance if it makes serialization or framework behavior harder. A shared trait is acceptable if that fits the project better.

## Part 7: Notification Reliability

### Problem

The meeting status is updated before notification sending.

If notification sending throws:

```text
status transition succeeds
notification fails
job retries
transition returns zero rows
notification may never be retried
```

### Preferred fix

Confirm these notifications implement `ShouldQueue`:

```text
MeetingStarted
MeetingEnded
```

If they do not, make them queued.

The webhook action should trigger the notification, but delivery should retry independently from the webhook job.

If needed, create:

```text
app/Services/Webhooks/ZoomMeetingWebhookNotifier.php
```

Methods:

```php
public function meetingStarted(Meeting $meeting, ?string $startTime): void;
public function meetingEnded(Meeting $meeting, ?string $startTime, ?string $endTime): void;
```

Use this only to remove duplicated notification-building logic.

Do not move lifecycle decisions into this notifier.

## Part 8: Logger Safety

`ZoomWebhookLogger` currently logs raw exception messages.

Review and sanitize messages.

Do not log:

```text
access tokens
refresh tokens
Authorization headers
webhook secret
Zoom start_url
raw credentials
```

At minimum:

```php
'message' => Str::limit(
    $this->sanitize($exception->getMessage()),
    1000,
),
```

Keep exception class and safe context.

## Part 9: Small Shared Helpers

Repeated code such as:

```php
$meeting->user()->value('uuid') ?: null;
```

may be moved to a small private helper in actions or a shared trait:

```php
private function userUuid(Meeting $meeting): ?string
{
    return $meeting->user()->value('uuid') ?: null;
}
```

Do not create a standalone service only for this.

## Part 10: Naming Consistency

Rename if practical:

```text
MeetingEndsWebhook
→ MeetingEndedWebhook
```

This matches:

```text
HandleMeetingEndedWebhook
MeetingEnded notification
meeting.ended event
```

Not critical, but improves consistency.

## Final Recommended Structure

```text
Controller
  → event-specific FormRequest
  → dispatcher

ZoomWebhookDispatcher
  → builds typed DTO
  → dispatches job

ZoomMeetingWebhookJob base class/trait
  → queue configuration
  → lock
  → retry/backoff
  → failed logging
  → tags

Concrete Job
  → event payload
  → calls action

Action
  → meeting lookup
  → sync lifecycle guard
  → atomic transition/update
  → event dispatch
  → notification trigger
  → processed/ignored/retry logging

Supporting services
  → ZoomWebhookLogger
  → optional ZoomMeetingWebhookNotifier
```

## Tests To Add Or Update

### Controller / Request

```text
- started route rejects meeting.ended event
- ended route rejects meeting.started event
- update route rejects wrong event
- delete route rejects wrong event
- valid signed event dispatches correct job
```

### Dispatcher / DTO

```text
- creates correct DTO for each event
- preserves int64-safe meeting ID
- update payload is normalized once
- request ID is passed through
```

### Jobs

```text
- each job calls the correct action
- jobs share same lock key format
- tries = 3
- backoff = [5, 30]
- failed() logs permanent failure
```

### Started action

```text
- missing meeting is ignored
- inactive sync status is ignored
- transition to started is atomic
- duplicate started event does not notify twice
- ended meeting is not restarted
- MeetingStatusUpdate event is dispatched once
```

### Ended action

```text
- missing meeting is ignored
- inactive sync status is ignored
- transition to ended is atomic
- duplicate ended event does not notify twice
- MeetingStatusUpdate event is dispatched once
```

### Updated action

```text
- missing meeting is ignored
- inactive sync status is ignored
- unsafe fields are excluded
- normalized equivalent values do not update
- changed safe fields are updated
```

### Deleted action

```text
- missing meeting is ignored
- deleting/deleted meeting is treated as already handled
- delete_failed can transition to deleted
- deleted status clears sync_error and sets synced_at
```

### Notification reliability

```text
- MeetingStarted implements ShouldQueue
- MeetingEnded implements ShouldQueue
- duplicate webhook does not enqueue duplicate notification
```

## Anti-Overengineering Rules

Do not:

```text
rewrite VerifyZoomWebhook
rewrite idempotency middleware
rewrite Zoom OAuth/Saloon integration
add event sourcing
add a webhook event table unless existing idempotency proves insufficient
build admin UI
move all action logic into a generic service
```

Focus only on:

```text
thin jobs
typed DTOs
event-specific validation
atomic transitions
notification reliability
duplicate removal
tests
```

---

# Important Performance Principle

Moving code from a job into an action or service does **not** make the queue job faster by itself.

The same PHP code still executes inside the same queue worker process.

The purpose of this structure is:

```text
readability
testability
clear responsibilities
reuse
easier maintenance
```

Actual job performance is mainly affected by:

```text
database query count
external API calls
notification delivery
large serialized payloads
heavy loops
file/network I/O
unnecessary relation loading
```

Do not create services merely to reduce the apparent size of a job.

Use this rule:

```text
Job
→ queue concerns only

Action
→ webhook business workflow

Supporting service
→ reusable or separate infrastructure responsibility
```

Keep business decisions in actions even if the action is longer than the job.

Move logic from an action into a service only when it is:

```text
reused by multiple actions
a clearly separate responsibility
substantial enough to obscure the main business flow
```

Good service candidates:

```text
ZoomWebhookLogger
ZoomMeetingWebhookNotifier
shared payload/DTO factories
shared query helper only if genuinely duplicated
```

Do not create unnecessary micro-services such as:

```text
WebhookMeetingFinder
WebhookStatusChecker
WebhookUserResolver
WebhookTransitionService
```

unless the codebase later proves they are needed.

---

# Performance-Focused Review

During implementation, review actual runtime costs instead of class size.

## 1. Queue notifications independently

Confirm these notifications implement `ShouldQueue`:

```text
MeetingStarted
MeetingEnded
```

The webhook action should only enqueue notification delivery.

Expected flow:

```text
webhook job
→ atomic meeting transition
→ enqueue notification
→ finish quickly
```

Do not keep slow mail/SMS delivery inside the webhook job.

## 2. Avoid repeated relation queries

Do not repeatedly call:

```php
$meeting->user()->value('uuid');
$meeting->project()->with(...)->firstOrFail();
```

multiple times in the same action.

Prefer loading once when needed:

```php
$meeting->loadMissing([
    'user',
    'project.user',
    'project.asignees',
]);
```

Then reuse loaded relations.

Do not eager-load notification relations for ignored/stale webhooks. First validate:

```text
meeting exists
sync_status accepted
atomic transition succeeded
```

Only then load project/member relations for notification work.

## 3. Keep job payloads small

Pass scalar values or small readonly DTOs into queued jobs.

Do not serialize:

```text
Request objects
services
closures
large provider payloads
unnecessary Eloquent models
```

Recommended job payload:

```text
meetingId
requestId
startTime/endTime when relevant
normalized whitelisted update changes
```

## 4. Normalize webhook update data once

Normalize and whitelist update fields before the job/action boundary or in one DTO factory.

Do not normalize the same payload in both dispatcher and action.

## 5. Do not query the database from the job handle before the action

The concrete job `handle()` should call the action directly.

Bad:

```text
job queries meeting
→ action queries meeting again
```

Good:

```text
job calls action
→ action owns the meeting lookup
```

The `failed()` hook may perform a small lookup for logging because it runs only after permanent failure.

## 6. Keep lock/retry configuration in jobs

Do not move these into services:

```text
tries
backoff
WithoutOverlapping
releaseAfter
expireAfter
tags
failed()
```

They are queue execution concerns.

## 7. Measure before further extraction

After the refactor, inspect:

```text
queries per webhook job
notification queue latency
job execution duration
failed/retried jobs
lock contention
```

Do not extract additional classes unless they improve clarity or remove proven duplication.

Additional anti-overengineering rules:

```text
- Do not move business logic into services merely to make the job appear smaller.
- Do not assume more classes improve runtime performance.
- Do not split every private action method into its own service.
- Optimize database/network work, not class placement.
```

Add this instruction to the implementation prompt:

```text
Important: moving logic from jobs into actions/services is for maintainability, not runtime speed. Keep queue concerns in jobs and business workflow in actions. Only extract a supporting service for reusable/separate responsibilities such as logging or notification construction. Optimize actual I/O, query count, payload size, and queued notification delivery instead of splitting code into more classes.
```
