# P2.2 - Durable Zoom Meeting Creation

## Goal

Prevent duplicate Zoom meetings when Zoom creates a meeting but Daywright does not receive or save the response.

The normal successful request must remain simple and synchronous. Recovery is used only when the result is uncertain.

## The problem being solved

The current creation flow is:

```text
Create local pending meeting
Call Zoom
Success response -> save Zoom details and mark active
Any exception -> mark failed
```

This is unsafe for one case:

```text
Zoom creates the meeting
Daywright loses the response or crashes before saving it
Daywright marks the local meeting failed
The user retries
A second Zoom meeting may be created
```

## Fixed design decisions

Implement these decisions exactly:

1. The local `meetings` row is the durable outbound operation record. Do not add a generic outbox table.
2. The Zoom API response is the normal success path.
3. A `meeting.created` webhook is the fast recovery path.
4. A scheduled Zoom lookup is the fallback when both the API response and webhook are unavailable.
5. Correlate with one stable `sync_operation_id` UUID sent to Zoom as a tracking field.
6. Never correlate automatically by topic, start time, or host alone.
7. Never repeat an uncertain Zoom create request automatically.
8. Keep the existing `MeetingOperationLock` and HTTP idempotency middleware.
9. Keep `meeting.started`, `meeting.updated`, `meeting.ended`, and `meeting.deleted` behavior unchanged.
10. A meeting can be started from Daywright only when `sync_status` is `active`. `MeetingTokenService` already enforces this.

This design follows `.ai/guidelines/backend-guidelines.md`. Use strict types, constructor injection, method injection in controllers, query-only repositories, `$guarded = []`, safe logs, and no unnecessary comments or abstractions.

## Confirmed Zoom behavior

SWE does not need to research these points:

- Zoom accepts `tracking_fields` when creating a scheduled meeting.
- Zoom documents `meeting.created` for API-created meetings with `creation_source = open_api`.
- The `meeting.created` payload can contain `tracking_fields` and the Zoom meeting ID.
- Zoom does not provide a create-meeting idempotency key that Daywright can rely on.
- Webhooks can be delayed, duplicated, or unavailable, so the webhook cannot be the only recovery mechanism.
- Daywright creates scheduled meetings. Set Zoom meeting `type` explicitly to `2`.

References:

- <https://developers.zoom.us/docs/api/meetings/>
- <https://developers.zoom.us/docs/api/meetings/events/>

## Required Zoom account configuration

Before production release:

1. Create a Zoom scheduling tracking field named exactly `Daywright Operation ID`.
2. Add this configuration:

```php
'meeting_operation_tracking_field' => env(
    'ZOOM_MEETING_OPERATION_TRACKING_FIELD',
    'Daywright Operation ID',
),
```

3. Subscribe the Zoom application to the `meeting.created` event.
4. Point that event to:

```text
POST /api/v1/webhooks/zoom/meetings/created
```

5. Perform one sandbox or test-account creation and confirm:
   - the create request accepts the tracking field;
   - the webhook has `creation_source = open_api`;
   - the webhook contains the same operation UUID.

If the connected Zoom account does not support the configured tracking field, stop automatic correlation. Keep the meeting in `create_unknown` for manual review. Do not replace exact correlation with topic/time matching.

The tracking field is a production prerequisite for automatic recovery. Do not silently resend the create request without it. If Zoom clearly rejects the configured tracking field, mark that local attempt `failed`, return a safe Zoom configuration error, and require the Zoom account administrator to configure the field before retrying. Supporting accounts without tracking fields would be a separate manual-only product mode and is outside P2.2.

## State lifecycle

Add these cases to `MeetingSyncStatus`:

```php
case Creating = 'creating';
case CreateUnknown = 'create_unknown';
```

Required creation transitions:

```text
pending -> creating
creating -> active
creating -> failed
creating -> create_unknown
create_unknown -> active
create_unknown -> failed
```

Meaning:

- `pending`: the local row is being prepared.
- `creating`: one Zoom create request is currently in progress.
- `active`: the Zoom meeting ID and required details were saved.
- `failed`: Zoom clearly rejected the creation request.
- `create_unknown`: Zoom may have created the meeting, so another POST is unsafe.

Only `active` accepts runtime Zoom webhooks and meeting start tokens.

## Error classification

Use these rules:

| Result                                                   | Local state                                          | Repeat Zoom POST?            |
| -------------------------------------------------------- | ---------------------------------------------------- | ---------------------------- |
| Valid Zoom success response                              | `active`                                             | No                           |
| Clear 4xx rejection from Zoom                            | `failed`                                             | A later user request is safe |
| Provider 429 response                                    | `failed`                                             | A later user request is safe |
| Timeout or connection loss                               | `create_unknown`                                     | No                           |
| Zoom 5xx response                                        | `create_unknown`                                     | No                           |
| Successful response with invalid/missing required fields | `create_unknown`                                     | No                           |
| Process crash while `creating`                           | Recovered as `create_unknown` after lease expiry     | No                           |
| Local database failure after Zoom may have accepted      | `creating` until lease expiry, then `create_unknown` | No                           |

Do not catch and hide programming errors. Only classified provider/transport uncertainty should return a recoverable meeting. Unexpected exceptions should still be reported and thrown; the expired creation lease protects the meeting if Zoom may have been called.

## Phase 1 - Add durable creation fields

Create one new migration. Do not edit an old migration and do not add `down()`.

Add nullable fields to `meetings`:

```text
sync_operation_id       UUID, unique
sync_claim_token        UUID
sync_started_at         timestamp
sync_lease_expires_at   timestamp
sync_available_at       timestamp
```

Reuse existing fields:

```text
sync_status
sync_attempts
sync_error
synced_at
```

Add indexes for recovery queries:

```text
(sync_status, sync_available_at)
(sync_status, sync_lease_expires_at)
```

Update:

- `app/Enums/Meeting/MeetingSyncStatus.php`
- `app/Models/Meeting.php`
- `tests/Unit/Models/MeetingStateMachineTest.php`
- `tests/Support/Meeting/MeetingTestHelper.php`
- the meeting factory if required

Add datetime casts for the three timestamps. Add only small, readable model scopes:

```text
createUnknownDueAt
creatingWithExpiredLeaseAt
readyForZoomRecoveryAt
```

Do not put recovery business logic in the model or a repository.

Phase 1 is complete when the migration and all state-transition tests pass.

## Phase 2 - Make the outbound create request durable

Update these files:

- `app/Actions/Meetings/CreateProjectMeeting.php`
- `app/Interfaces/Zoom.php`
- `app/Services/Zoom/ZoomService.php`
- `app/Services/Zoom/ZoomServiceFake.php`
- `app/Http/Integrations/Zoom/Requests/CreateMeeting.php`
- `config/services.php`
- `app/Http/Controllers/Api/V1/Project/MeetingsController.php`

### Prepare the local operation

Inside the existing `MeetingOperationLock`:

1. Generate one UUID for `sync_operation_id`.
2. In one short database transaction:
   - create the local meeting directly in its initial `creating` state;
   - assign the operation UUID;
   - set `sync_started_at = now()`;
   - set `sync_lease_expires_at = now()->addMinutes(5)`.
3. Commit before calling Zoom.
4. Call Zoom outside the transaction.

Do not dispatch a job or call Zoom while the transaction is open.

### Send the operation ID to Zoom

Change the create method signature to accept the operation ID explicitly:

```php
public function createMeeting(
    array $validated,
    User $user,
    string $operationId,
): Meeting;
```

In `CreateMeeting::defaultBody()`, include:

```php
'type' => 2,
'tracking_fields' => [[
    'field' => config('services.zoom.meeting_operation_tracking_field'),
    'value' => $this->operationId,
]],
```

Do not add `sync_operation_id` to `MeetingStoreData::toArray()`. It is internal state, not user input.

### Handle success

On a valid Zoom response, update the existing local row:

```text
meeting_id
start_url
join_url
status
sync_status = active
synced_at = now
sync_error = null
sync_claim_token = null
sync_lease_expires_at = null
sync_available_at = null
```

If a `meeting.created` webhook already attached the same `meeting_id`, finish normally. If it attached a different ID, do not overwrite it; keep `create_unknown`, record a safe conflict code, and require review.

### Handle definite rejection

For a clear Zoom 4xx or 429 response:

1. Transition `creating -> failed`.
2. Increment `sync_attempts`.
3. Store a safe error code/class, not the raw message.
4. Clear the lease.
5. Re-throw the existing public-safe exception.

### Handle an uncertain result

For a timeout, connection failure, Zoom 5xx, or malformed successful response:

1. Transition `creating -> create_unknown`.
2. Increment `sync_attempts`.
3. Set `sync_available_at = now()->addMinute()`.
4. Clear the active lease.
5. Report the exception.
6. Log only safe identifiers and exception class/code.
7. Return the local meeting instead of throwing a generic failure.

Use one specific integration exception such as `ZoomMeetingCreationUnknownException` so `CreateProjectMeeting` does not contain a large exception classifier. `ZoomService::createMeeting()` should translate uncertain transport/provider outcomes to this exception. Do not introduce a general integration exception framework.

### HTTP response

Keep the current successful response unchanged:

```text
active -> 201 Created
```

Return the same `MeetingResource` with:

```text
create_unknown -> 202 Accepted
```

The resource already exposes `sync_status`. Do not expose `sync_error`, claim tokens, operation IDs, or leases.

The existing idempotency middleware remains required. A repeated request with the same idempotency key must replay the same `201` or `202` response without another Zoom POST.

No middleware implementation change is required. The installed middleware serializes every completed response with its original status, body, and headers, including `202`. Add a regression test proving that a repeated key replays `202`, includes `Idempotency-Replayed: true`, creates one local row, and sends one Zoom request.

Phase 2 is complete when success, definite rejection, and uncertain-result tests pass.

## Phase 3 - Add `meeting.created` as the fast recovery path

Reuse the existing durable webhook inbox. Do not create another webhook table or another retry system.

Add:

- `app/DataTransferObjects/Zoom/MeetingCreatedWebhookData.php`
- `app/Http/Requests/Api/V1/Zoom/MeetingCreatedWebhookRequest.php`
- `app/Actions/Webhooks/Zoom/HandleMeetingCreatedWebhook.php`
- route `POST webhooks/zoom/meetings/created`

Update:

- `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`
- `app/Services/Webhooks/ZoomWebhookInboxService.php`
- `app/Actions/Webhooks/Zoom/HandlePersistedZoomWebhookAction.php`
- `database/factories/WebhookInboxFactory.php`
- `tests/Support/Zoom/ZoomWebhookPayloadFactory.php`

The DTO needs only:

```text
meetingId
operationId
joinUrl
creationSource
requestId
```

Extract `operationId` from the tracking field whose name equals the configured `Daywright Operation ID` field. Tracking fields are optional because Zoom can send `meeting.created` for meetings not created by Daywright.

The handler must:

1. Ignore events whose `creationSource` is not `open_api`.
2. Ignore events without the configured operation tracking field.
3. Find the local meeting by exact `sync_operation_id`.
4. If no local meeting exists, log a safe ignored reason and complete normally.
5. If the meeting is already `active` with the same ID, do nothing.
6. If a different Zoom ID is already stored, do not overwrite it; record/report a conflict.
7. For `creating` or `create_unknown`, save `meeting_id` and `join_url`, then make recovery immediately due.

Do not mark the meeting `active` from the webhook alone. The webhook does not provide every canonical field currently required by Daywright, especially a fresh host `start_url`. The scheduled recovery action will fetch the full meeting by its newly known ID and finalize it.

Do not change missing-meeting behavior for runtime webhooks. A `create_unknown` meeting cannot be started through Daywright because `MeetingTokenService` requires `active`.

This closes the supported timing case: Daywright cannot issue a start token between `meeting.created` attaching the ID and recovery marking the meeting `active`. If someone starts the meeting directly in Zoom during that window, the runtime webhook is ignored because the meeting is not active. That external-start case is outside P2.2. Log the existing safe `inactive_sync_status` reason; do not add runtime-webhook deferral or an orphan-event table in this phase.

Phase 3 is complete when the new webhook passes signature, acceptance, deduplication, processing, and idempotency tests through the existing inbox.

## Phase 4 - Add scheduled reconciliation

Add:

- `app/Actions/Meetings/FindZoomMeetingForRecovery.php`
- `app/Actions/Meetings/RecoverAmbiguousZoomMeeting.php`
- `app/Actions/Meetings/ResolveAmbiguousZoomMeetingManually.php`
- `app/Actions/Meetings/FinalizeZoomMeetingRecovery.php`
- `app/Console/Commands/RecoverAmbiguousZoomMeetings.php`
- `app/Http/Integrations/Zoom/Requests/GetMeeting.php`
- `app/Http/Integrations/Zoom/Requests/ListMeetings.php`

Update:

- `app/Interfaces/Zoom.php`
- `app/Services/Zoom/ZoomService.php`
- `app/Services/Zoom/ZoomServiceFake.php`
- `app/Console/Kernel.php`

### Recovery selection

Select only:

- `create_unknown` rows whose `sync_available_at` is due; or
- `creating` rows whose five-minute creation lease expired.

Use a small default command limit such as `25`.

### Atomic claim

For each selected meeting:

1. Generate a new UUID claim token.
2. Atomically update the row only if it is still eligible and not actively leased.
3. Save the claim token and a five-minute lease.
4. Call Zoom outside a database transaction.
5. Guard the final update by local meeting ID, claim token, and unexpired lease.

Use the same straightforward conditional-update approach already used by `ZoomWebhookInboxService`. Do not extract a shared claim framework.

### Exact recovery lookup

Use this order:

1. If `meeting_id` is already known from the webhook, call `GET /meetings/{meetingId}`.
2. Otherwise list the connected user's scheduled, unexpired meetings with `GET /users/me/meetings`.
3. Follow pagination so a match is not missed.
4. Match the configured tracking-field name and exact operation UUID.
5. Do not assume a list item always contains `tracking_fields`. When it does not, fetch that meeting with `GET /meetings/{meetingId}` and inspect the full response.
6. If one match exists, use its full `GET /meetings/{meetingId}` response to finalize the local meeting.

Results:

- Exactly one verified match: save canonical details and transition to `active`.
- No match: keep `create_unknown` and schedule another lookup.
- More than one exact match: keep `create_unknown`, stop automatic recovery, and require manual review.
- Zoom lookup failure: keep `create_unknown` and schedule another lookup.

The recovery action must never call `POST /users/me/meetings`.

### Retry policy

Use this explicit lookup backoff:

```php
1 => 60,
2 => 300,
3 => 900,
4 => 3600,
```

Maximum automatic attempts: `5`.

After attempt 5:

```text
sync_status = create_unknown
sync_available_at = null
sync_error = manual_review_required
```

This is not a definite creation failure, so do not change it to `failed` merely because recovery could not prove the result.

### Command behavior

Command:

```text
php artisan meetings:recover-ambiguous --limit=25
```

Process rows one at a time. Catch failures inside the loop so one bad meeting does not stop the command.

Report these counts:

```text
selected
recovered
unresolved
manual_review
failed
```

Schedule it in `app/Console/Kernel.php`:

```php
$schedule->command('meetings:recover-ambiguous --limit=25')
    ->name('recover-ambiguous-zoom-meetings')
    ->onOneServer()
    ->withoutOverlapping()
    ->everyMinute()
    ->appendOutputTo($this->schedulerLogPath());
```

Phase 4 is complete when crash recovery, concurrent claims, backoff, and terminal manual-review tests pass.

## Phase 5 - Operational behavior

Safe logs may contain only:

```text
local meeting ID
user ID or UUID
sync operation ID
sync status
attempt number
exception class
exception code
```

Never log:

```text
Zoom access or refresh tokens
meeting password
start_url or join_url
raw Zoom request or response body
webhook payload or signature
raw exception message
```

Operators must be able to query:

```sql
sync_status = 'create_unknown' AND sync_available_at IS NULL
```

That query means automatic recovery ended and manual review is required.

Manual review is part of P2.2 and must not rely on direct SQL updates. Add:

```text
local-docs/operations/zoom-ambiguous-meeting-recovery.md
```

Also add one internal command:

```text
php artisan meetings:resolve-ambiguous {meetingId}
    --zoom-meeting-id=<id>
    --mark-failed
    --reference=<ticket-or-incident-id>
```

Require `--reference` and exactly one resolution option:

- `--zoom-meeting-id`: fetch the Zoom meeting, verify the connected owner and exact operation tracking field, then finalize through the manual resolution action.
- `--mark-failed`: display an interactive warning and transition only `create_unknown -> failed`. It must never call the Zoom create endpoint.

The command must record a safe structured operational entry containing the local meeting ID, chosen resolution, reference, and remote meeting ID when supplied. Do not log URLs, passwords, tokens, or provider payloads.

The runbook must tell the operator to:

1. Inspect the local `create_unknown` row and its operation ID.
2. Check the connected host's Zoom meetings.
3. If an exact operation ID match exists, run the command with `--zoom-meeting-id`.
4. If the operator can prove no Zoom meeting exists, run the command with `--mark-failed`.
5. If the result is still uncertain, leave the row unchanged and escalate it.

Do not provide a public endpoint that forces `create_unknown` back to `pending`.

## Phase 6 - Required tests

### State and database tests

- `pending -> creating` is valid.
- `creating -> active`, `failed`, and `create_unknown` are valid.
- `create_unknown -> active` and `failed` are valid.
- `create_unknown -> creating` is invalid.
- `sync_operation_id` is unique.
- recovery timestamps are cast correctly.

### Outbound creation tests

Update `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`:

- Successful Zoom response returns `201` and stores an active meeting.
- The Zoom request receives the stable operation UUID.
- A clear Zoom rejection marks the local row failed.
- A timeout returns `202` and stores `create_unknown`.
- A connection failure returns `202` and stores `create_unknown`.
- A Zoom 5xx returns `202` and stores `create_unknown`.
- A malformed successful response returns `202` and stores `create_unknown`.
- Retrying the same HTTP idempotency key does not send another Zoom POST.
- Replaying a `202` preserves the status and response and includes `Idempotency-Replayed: true`.
- A `create_unknown` meeting cannot obtain a start token.
- A successful response arriving after the created webhook is harmless.
- Conflicting remote meeting IDs are never overwritten.

Update `tests/Unit/Http/Integrations/Zoom/ZoomRequestsTest.php`:

- Create request explicitly sends `type = 2`.
- Create request sends the configured tracking-field name and operation UUID.

### Created-webhook tests

Update or add tests beside the existing Zoom webhook tests:

- `meeting.created` is accepted into the durable inbox.
- Duplicate delivery creates only one inbox row.
- A valid operation UUID attaches the remote meeting ID.
- A non-`open_api` event is ignored.
- A missing operation tracking field is ignored.
- An unknown operation UUID is ignored safely.
- Reprocessing the same event is harmless.
- A conflicting remote ID is reported and not overwritten.
- Queue dispatch failure leaves the inbox recoverable.

### Recovery tests

Add `tests/Feature/Console/Meetings/RecoverAmbiguousZoomMeetingsTest.php`:

Keep focused claim, lookup, backoff, and finalization cases in `tests/Feature/Actions/Meetings/RecoverAmbiguousZoomMeetingTest.php`. Keep command selection and output behavior in the command test.

- An expired `creating` row becomes recoverable.
- A known remote ID is fetched and finalized.
- One exact tracking-field match is finalized.
- No match remains `create_unknown` and receives backoff.
- Multiple exact matches stop for manual review.
- Two workers cannot claim the same meeting.
- An expired recovery claim can be reclaimed.
- A stale claim token cannot finalize the row.
- Attempt 5 stops automatic recovery.
- Recovery never sends a create request.
- One failed row does not stop later rows.
- Manual resolution verifies the remote owner and operation ID before activation.
- Manual failure resolution requires confirmation and never calls Zoom create.

## File checklist

### New files

```text
app/Actions/Meetings/FindZoomMeetingForRecovery.php
app/Actions/Meetings/RecoverAmbiguousZoomMeeting.php
app/Actions/Meetings/ResolveAmbiguousZoomMeetingManually.php
app/Actions/Meetings/FinalizeZoomMeetingRecovery.php
app/Actions/Webhooks/Zoom/HandleMeetingCreatedWebhook.php
app/Console/Commands/RecoverAmbiguousZoomMeetings.php
app/Console/Commands/ResolveAmbiguousZoomMeeting.php
app/DataTransferObjects/Zoom/MeetingCreatedWebhookData.php
app/Exceptions/Integrations/Zoom/ZoomMeetingCreationUnknownException.php
app/Http/Integrations/Zoom/Requests/GetMeeting.php
app/Http/Integrations/Zoom/Requests/ListMeetings.php
app/Http/Requests/Api/V1/Zoom/MeetingCreatedWebhookRequest.php
database/migrations/<timestamp>_add_zoom_creation_recovery_fields_to_meetings_table.php
tests/Feature/Console/Meetings/RecoverAmbiguousZoomMeetingsTest.php
tests/Feature/Console/Meetings/ResolveAmbiguousZoomMeetingTest.php
tests/Feature/Actions/Meetings/RecoverAmbiguousZoomMeetingTest.php
local-docs/operations/zoom-ambiguous-meeting-recovery.md
```

### Existing files to update

```text
app/Actions/Meetings/CreateProjectMeeting.php
app/Actions/Webhooks/Zoom/HandlePersistedZoomWebhookAction.php
app/Console/Kernel.php
app/Enums/Meeting/MeetingSyncStatus.php
app/Http/Controllers/Api/V1/Project/MeetingsController.php
app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php
app/Http/Integrations/Zoom/Requests/CreateMeeting.php
app/Interfaces/Zoom.php
app/Models/Meeting.php
app/Services/Webhooks/ZoomWebhookInboxService.php
app/Services/Zoom/ZoomService.php
app/Services/Zoom/ZoomServiceFake.php
config/services.php
database/factories/WebhookInboxFactory.php
routes/api/v1/webhooks.php
tests/Feature/Api/V1/Meetings/MeetingCreateTest.php
tests/Feature/Api/Webhooks/Zoom/WebhookInboxTest.php
tests/Support/Meeting/MeetingTestHelper.php
tests/Support/Zoom/ZoomWebhookPayloadFactory.php
tests/Unit/Http/Integrations/Zoom/ZoomRequestsTest.php
tests/Unit/Models/MeetingStateMachineTest.php
```

## Do not implement

- A generic `IntegrationOperation` model.
- A generic provider registry.
- A second webhook inbox.
- An orphan-webhook table.
- Automatic matching by topic, time, email, or host.
- Automatic recreation after an uncertain result.
- A public force-retry endpoint.
- Changes to existing runtime webhook behavior.
- A database transaction held open during a Zoom request.

## Verification commands

Run focused tests while implementing:

```text
php artisan test tests/Unit/Models/MeetingStateMachineTest.php
php artisan test tests/Unit/Http/Integrations/Zoom/ZoomRequestsTest.php
php artisan test tests/Feature/Api/V1/Meetings/MeetingCreateTest.php
php artisan test tests/Feature/Api/Webhooks/Zoom
php artisan test tests/Feature/Services/Webhooks/ZoomWebhookInboxServiceTest.php
php artisan test tests/Feature/Console/Meetings/RecoverAmbiguousZoomMeetingsTest.php
php artisan test tests/Feature/Console/Meetings/ResolveAmbiguousZoomMeetingTest.php
```

Run final gates:

```text
composer test
composer stan
composer pint:test
composer rector:test
```

## Completion criteria

P2.2 is complete only when:

- successful creation still returns `201`;
- ambiguous creation returns `202` with `create_unknown`;
- a stable operation UUID is sent to Zoom;
- `meeting.created` can attach the remote ID through the durable inbox;
- scheduled recovery can finalize the meeting when the webhook is unavailable;
- no uncertain path sends a second Zoom create request;
- exhausted recovery is visible as manual review;
- manual review has a tested command and operational runbook;
- runtime webhook behavior remains unchanged;
- focused tests and all final quality gates pass;
- the Zoom account configuration has been smoke-tested.
