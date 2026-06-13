# Plan: Make Zoom Webhooks Production Ready

## Purpose

Review and harden the existing Laravel + Zoom webhook integration so it is production-safe.

The app already has:

- Laravel API routes for Zoom meeting webhooks.
- `VerifyZoomWebhook` middleware applied to every Zoom webhook request.
- `wendelladriel/laravel-idempotency` package applied to webhook routes with global scope.
- Queued jobs for Zoom meeting update/delete/start/ended events.
- `WithoutOverlapping` middleware in jobs using Zoom meeting ID.
- Meeting sync lifecycle statuses already introduced elsewhere.

Do not rewrite the Zoom integration from scratch. Review current code, fix critical edge cases, and add/update tests.

---

## Main Files To Inspect First

```text
routes/api.php
or the route file where Zoom webhook routes are registered

app/Http/Middleware/VerifyZoomWebhook.php
app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php
app/Http/Requests/Api/V1/Zoom/WebhookRequest.php

app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php
app/Jobs/Webhooks/Zoom/MeetingEndsWebhook.php
app/Jobs/Webhooks/Zoom/UpdateMeetingWebhook.php
app/Jobs/Webhooks/Zoom/DeleteMeetingWebhook.php
app/Jobs/Webhooks/Zoom/Concerns/InteractsWithZoomWebhookLogging.php

app/DataTransferObjects/Zoom/MeetingWebhookUpdateData.php
app/Enums/Meeting/MeetingSyncStatus.php
app/Enums/MeetingState.php
app/Models/Meeting.php

config/idempotency.php
config/services.php

tests covering:
- Zoom webhook middleware
- Zoom webhook controller
- Zoom webhook jobs
- idempotency
- meeting sync lifecycle/status behavior
```

---

## Current Route Context

The webhook routes are expected to look like this:

```php
Route::controller(ZoomWebhookController::class)
    ->middleware([
        VerifyZoomWebhook::class,
        Idempotent::using(scope: IdempotencyScope::Global),
    ])
    ->prefix('webhooks/zoom/meetings')
    ->as('webhooks.meetings.')
    ->group(function (): void {
        Route::post('update', 'update')->name('update');
        Route::post('delete', 'delete')->name('delete');
        Route::post('start', 'start')->name('start');
        Route::post('ended', 'ended')->name('ended');
    });
```

Important:

- `VerifyZoomWebhook` should run before the idempotency middleware.
- `VerifyZoomWebhook` should copy Zoom's `x-zm-request-id` into the configured idempotency header.
- Webhooks should use `IdempotencyScope::Global`, not user scope.

---

## Expected Middleware Behavior

`VerifyZoomWebhook` should:

```text
1. Require x-zm-request-id.
2. Require x-zm-signature.
3. Require x-zm-request-timestamp.
4. Reject timestamps older/newer than allowed window, usually 5 minutes.
5. Generate signature from raw request body:
   v0:{timestamp}:{raw_body}
6. Compare with x-zm-signature using hash_equals.
7. Support endpoint.url_validation event and return:
   plainToken + encryptedToken.
8. Set configured Idempotency-Key header from x-zm-request-id.
9. Abort safely if webhook secret is missing.
```

Add explicit guard:

```php
$secret = config('services.zoom.webhook_secret');

if (! is_string($secret) || trim($secret) === '') {
    abort(500, 'Zoom webhook secret is not configured.');
}
```

Do not log webhook secret, request signature, access tokens, refresh tokens, start_url, or Authorization headers.

---

## Important Production Rules

### 1. Zoom meeting ID type

Zoom meeting IDs are external IDs and can be int64/more than 10 digits.

Do not cast Zoom meeting IDs to normal int in webhook jobs.

Use one of these:

```php
public int|string $meeting_id;
```

or:

```php
public string $meeting_id;
```

Recommended:

- If DB column is `unsignedBigInteger`, `int|string` is acceptable.
- If DB column is string, normalize all webhook meeting IDs to string.

Search for this pattern and fix:

```php
$this->meeting_id = (int) $data['meeting_id'];
```

Use:

```php
$this->meeting_id = $data['meeting_id'];
```

or:

```php
$this->meeting_id = (string) $data['meeting_id'];
```

Make this consistent across:

```text
StartMeetingWebhook
MeetingEndsWebhook
UpdateMeetingWebhook
DeleteMeetingWebhook
```

---

### 2. HTTP idempotency is not enough by itself

The idempotency middleware should prevent duplicate HTTP webhook delivery from dispatching duplicate jobs when the same `x-zm-request-id` is repeated.

Still keep job-level guards because they protect against:

```text
- manual job retries
- queue retries
- lock expiry
- different stale events arriving close together
- duplicate notifications if status transition is not atomic
```

Final protection stack should be:

```text
signature verification
→ HTTP idempotency
→ queued job
→ WithoutOverlapping
→ status/sync_status guards
→ atomic database transition
```

---

### 3. Job `WithoutOverlapping` key

Jobs should lock by Zoom meeting ID:

```php
new WithoutOverlapping(key: "zoom-meeting:{$this->meeting_id}")
```

This is good.

Confirm:

- `shared()` is used if different job classes should share the same meeting lock.
- `expireAfter()` is longer than the expected job duration.
- `releaseAfter()` is reasonable.

Current values like `releaseAfter: 5` and `expireAfter: 120` are acceptable.

---

## Critical Edge Cases To Fix

### 1. Start/End duplicate notifications

Current pattern may be:

```text
check meeting status
→ update meeting status
→ send notifications
```

Make the status transition atomic to avoid duplicate notifications.

Preferred approach for start:

```php
$updated = Meeting::query()
    ->whereKey($meeting->getKey())
    ->where('status', '!=', MeetingState::START->value)
    ->where('status', '!=', MeetingState::ENDS->value)
    ->update([
        'status' => MeetingState::START->value,
    ]);

if ($updated === 0) {
    // already started, ended, or stale
    log ignored
    return;
}

$meeting->refresh();

event(new MeetingStatusUpdate($meeting));
send notifications
```

For ended:

```php
$updated = Meeting::query()
    ->whereKey($meeting->getKey())
    ->where('status', '!=', MeetingState::ENDS->value)
    ->update([
        'status' => MeetingState::ENDS->value,
    ]);

if ($updated === 0) {
    log ignored
    return;
}

$meeting->refresh();

event(new MeetingStatusUpdate($meeting));
send notifications
```

The exact implementation can be adjusted to existing style.

Goal:

- Do not send duplicate notifications for repeated start/ended webhook.
- Do not allow a stale start event to revive an already ended meeting.

---

### 2. Respect meeting sync lifecycle

With the new meeting sync lifecycle, start/end/update/delete webhooks should not revive or modify meetings that are not active/synced.

Add guards such as:

```text
If sync_status is deleting/deleted/delete_failed/failed/pending
→ ignore start/end/update as stale or not applicable.
```

Recommended helper on `MeetingSyncStatus` enum:

```php
public function acceptsZoomRuntimeWebhook(): bool
{
    return $this === self::Active;
}
```

Or use simple checks in job.

For example:

```php
if ($meeting->sync_status !== MeetingSyncStatus::Active) {
    log ignored stale_event or inactive_sync_status
    return;
}
```

Do this especially for:

- StartMeetingWebhook
- MeetingEndsWebhook
- UpdateMeetingWebhook

For DeleteMeetingWebhook:

- If already deleted/deleting, treat as already handled.

---

### 3. Delete webhook should update lifecycle before delete

Do not only call:

```php
$meeting->delete();
```

Prefer:

```php
$meeting->update([
    'sync_status' => MeetingSyncStatus::Deleted,
    'sync_error' => null,
    'synced_at' => now(),
]);
```

Then:

- If `Meeting` uses SoftDeletes, call `$meeting->delete()`.
- If it does not use SoftDeletes, keep the row with `sync_status = deleted`.

Normal index should exclude non-active meetings.

---

### 4. Update webhook must whitelist fields

`UpdateMeetingWebhook` should only update safe meeting fields normalized by `MeetingWebhookUpdateData`.

Confirm `MeetingWebhookUpdateData::normalizeChanges()` only allows expected fields, such as:

```text
topic
agenda
duration
start_time
timezone
password
join_before_host
status
```

It must not allow:

```text
id
user_id
project_id
sync_status
sync_error
sync_attempts
synced_at
created_at
updated_at
start_url
meeting_id
```

unless intentionally handled.

If whitelist does not exist, implement one.

---

### 5. Update webhook field comparison should normalize types

Current direct strict comparison can be wrong:

```php
$value !== $meeting->$key
```

Potential false positives:

```text
"30" vs 30
1 vs true
"2026-06-08T10:00:00Z" vs "2026-06-08 10:00:00"
```

Fix by normalizing values before comparison.

Possible approaches:

- Normalize all incoming fields inside `MeetingWebhookUpdateData`.
- Compare using Laravel model casts.
- Use field-specific comparison.

Minimum safer approach:

```php
private function hasChanged(Meeting $meeting, string $key, mixed $value): bool
{
    $current = $meeting->getAttribute($key);

    if ($key === 'start_time') {
        return optional($current)->toISOString() !== Carbon::parse($value)->toISOString();
    }

    return $value != $current;
}
```

Use stricter field-specific logic if possible.

---

### 6. Controller dispatch consistency

The controller currently may use `dispatch()` for update/delete and `dispatchAfterResponse()` for start/ended.

Standardize one approach.

Recommended for webhooks:

```php
JobClass::dispatchAfterResponse([...]);
```

for all webhook jobs, because webhook endpoints should respond quickly.

If queue behavior in the project prefers normal `dispatch()`, use it consistently and ensure it still returns quickly.

---

### 7. Endpoint validation should bypass controller idempotency correctly

The `endpoint.url_validation` event is handled inside `VerifyZoomWebhook` and returns JSON before the controller.

Confirm this still works with idempotency middleware order:

- Verify middleware should return validation response before idempotency middleware if it handles validation early.
- This is acceptable.

Add test for endpoint validation.

---

### 8. Webhook request validation

Inspect `WebhookRequest`.

Ensure it validates enough for each route/event:

```text
payload.object.id required
event expected if used
payload.object.start_time optional string/date for start
payload.object.end_time optional string/date for ended
update payload shape accepted safely
```

Do not trust arbitrary update payload fields.

---

## Tests To Add / Update

Follow existing project test style.

### Middleware tests

```text
- rejects missing x-zm-request-id
- rejects missing x-zm-signature
- rejects missing x-zm-request-timestamp
- rejects stale timestamp older than allowed window
- rejects invalid signature
- accepts valid signature
- handles endpoint.url_validation and returns plainToken/encryptedToken
- sets configured Idempotency-Key header from x-zm-request-id
- fails clearly when webhook secret is missing
```

### Route/idempotency tests

```text
- duplicate webhook request with same x-zm-request-id does not dispatch job twice
- webhook idempotency uses global scope
- repeated webhook with same request id returns cached/accepted response
- different x-zm-request-id dispatches a new job
```

Use fake queues where appropriate:

```php
Queue::fake();
```

Assert dispatch count.

### Start webhook job tests

```text
- missing meeting is ignored
- active meeting becomes started
- MeetingStatusUpdate event is dispatched once
- notification is sent once
- duplicate start webhook does not send duplicate notification
- start webhook is ignored if meeting already ended
- start webhook is ignored if sync_status is not active
```

### Ended webhook job tests

```text
- missing meeting is ignored
- active/started meeting becomes ended
- MeetingStatusUpdate event is dispatched once
- notification is sent once
- duplicate ended webhook does not send duplicate notification
- ended webhook is ignored if already ended
- ended webhook is ignored if sync_status is not active
```

### Update webhook job tests

```text
- missing meeting is ignored
- no-op update is ignored
- safe changed fields are updated
- unsafe fields are ignored
- type-normalized equivalent values do not trigger update
- update is ignored if sync_status is not active
```

### Delete webhook job tests

```text
- missing meeting is ignored
- active meeting is marked deleted
- if SoftDeletes exists, meeting is soft deleted
- if SoftDeletes does not exist, meeting remains with sync_status deleted
- duplicate delete webhook is safe
- already deleted/deleting meeting is ignored or treated as already handled
```

### Queue/retry tests

```text
- jobs have tries/backoff configured
- WithoutOverlapping key uses Zoom meeting ID
- failed() logs failure without throwing new errors
```

---

## Production Readiness Checklist

Before finishing, confirm:

```text
[ ] VerifyZoomWebhook validates signature using raw body.
[ ] Timestamp replay protection exists.
[ ] endpoint.url_validation works.
[ ] Webhook secret missing is handled explicitly.
[ ] x-zm-request-id is required.
[ ] x-zm-request-id becomes Idempotency-Key.
[ ] Idempotency middleware is global-scoped.
[ ] Duplicate HTTP webhooks do not dispatch duplicate jobs.
[ ] Jobs use int64-safe Zoom meeting ID typing.
[ ] Jobs use WithoutOverlapping with shared meeting key.
[ ] Start/end transitions are atomic enough to prevent duplicate notifications.
[ ] Update webhook fields are whitelisted.
[ ] Update comparison normalizes types/formats.
[ ] Stale webhooks do not revive deleted/failed/pending meetings.
[ ] Delete webhook respects new meeting sync lifecycle.
[ ] Tests cover security, idempotency, stale events, duplicates, and failure paths.
```

---

## Anti-Overengineering Rules

Do not do these unless absolutely needed:

```text
- Do not rewrite Zoom OAuth flow.
- Do not rewrite Saloon connector/request classes.
- Do not add a new webhook events table unless current idempotency package is insufficient.
- Do not introduce a complex event-sourcing system.
- Do not build admin UI.
- Do not queue nested jobs unless existing architecture already needs it.
```

Focus on:

```text
security
idempotency
lifecycle consistency
duplicate notification prevention
tests
```

---

## Suggested Prompt For Devin/Windsurf

Use this prompt:

```text
Read PLAN.md first.

I have a Laravel app with an existing Saloon-based Zoom integration and Zoom meeting webhooks. The webhook routes use VerifyZoomWebhook middleware and the wendelladriel/laravel-idempotency package with IdempotencyScope::Global.

Please inspect the files listed in PLAN.md, then review and harden the webhook system for production readiness.

Do not rewrite the Zoom integration from scratch.

Focus on:
1. webhook signature/security validation,
2. global idempotency using x-zm-request-id,
3. int64-safe Zoom meeting ID handling,
4. job-level guards and WithoutOverlapping,
5. atomic start/end transitions to prevent duplicate notifications,
6. lifecycle guards using MeetingSyncStatus,
7. safe update webhook field whitelisting,
8. delete webhook lifecycle behavior,
9. tests for critical edge cases.

Before editing, summarize what you found and list the exact files you plan to change. Then implement in small patches and add/update tests.
```
