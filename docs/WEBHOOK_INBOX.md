# Webhook Inbox - Reliability and Operations

## Overview

The webhook inbox provides durable, at-least-once processing of third-party webhook events with automatic recovery from failures. The current implementation is used by Zoom; the reliability rules in this document are provider-neutral unless a section is explicitly marked as Zoom-specific.

Provider onboarding and future integration work should follow [Webhook Provider Onboarding](WEBHOOK_PROVIDER_ONBOARDING.md).

## Start here

For one event, follow this path: Zoom sends an HTTP request, middleware verifies it, the controller stores a normalized event in the inbox, a queue worker runs the event handler, and the scheduled recovery command redispatches events that were not completed. The controller acknowledges a request only after the inbox has saved it. Business handling is asynchronous and may run more than once, so handlers must be safe to retry.

The main code path is [VerifyZoomWebhook](../app/Http/Middleware/VerifyZoomWebhook.php) → [ZoomWebhookController](../app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php) → [ZoomWebhookInboxService](../app/Services/Webhooks/ZoomWebhookInboxService.php) → [ProcessZoomWebhookInbox](../app/Jobs/Webhooks/ProcessZoomWebhookInbox.php) → [HandlePersistedZoomWebhookAction](../app/Actions/Webhooks/Zoom/HandlePersistedZoomWebhookAction.php) → an event handler. Start with this flow before reading the detailed operations sections below.

## Architecture

### Components

- **WebhookInbox Model**: Database table storing all received webhooks
- **Provider webhook boundary**: Verifies the provider request and converts it into normalized metadata
- **Inbox service**: Orchestrates acceptance, processing, and recovery
- **Provider processing job**: Queue worker for processing a provider's inbox events
- **RecoverPendingWebhooks Command**: Scheduled recovery command
- **WebhookInboxState Enum**: State machine (Received, Processing, Completed, Failed)

### Data Flow

1. **Acceptance**: Provider request → verification → controller → inbox service → database
2. **Processing**: Database → queue → worker → provider handler → business actions
3. **Recovery**: Scheduler → Command → Queue → Worker

## Common Provider Contract

Every provider integration must supply the following information to the inbox workflow:

- authenticated provider request
- deterministic event key
- provider name
- event type and optional schema version
- encrypted payload required for replay
- provider event ID or safe correlation ID when available
- provider occurrence time when available

The provider implementation owns signature verification, timestamp tolerance, payload validation, event-key construction, event-to-handler mapping, acknowledgement requirements, and provider-specific API calls. The inbox owns durable persistence, uniqueness, claiming, leases, retries, and recovery.

Do not assume that providers share the same event IDs, signature algorithms, ordering guarantees, retry schedules, or acknowledgement rules.

### Zoom timestamp handling

Zoom event timestamps are normalized at the inbox boundary and stored in `provider_occurred_at` as milliseconds since the Unix epoch. The Zoom adapter accepts a seconds or milliseconds value from the provider and converts it before persistence, so meeting handlers compare one consistent format.

`last_zoom_event_timestamp` orders provider events. An update event strictly older than the stored timestamp is ignored. A distinct update with an equal timestamp is reconciled against Zoom's current state; the inbox fingerprint still deduplicates an exact repeat delivery. A delete event older than the stored timestamp is ignored, while a distinct delete at the same timestamp wins an update/delete tie. Recoverable rows may be retried, so handlers must remain safe to run more than once.

For update events, `sync_reconcile_before_at` is a local-time cutoff. An event at or before this cutoff, an event tied with the provider watermark, or a callback matching a pending update triggers a read of Zoom's current meeting state. The handler makes the Zoom request outside the database transaction, then locks the local meeting and rechecks the provider watermark, operation ID, and cutoff before using the snapshot. If Zoom confirms the meeting is missing, the local meeting is marked deleted. The cutoff triggers reconciliation; it does not reject events. Clock differences can affect whether the handler makes the extra Zoom read, but provider event ordering itself uses only provider timestamps.

`event_ts` is required for `meeting.updated` and optional for `meeting.deleted`. When a delete event has no timestamp, the handler trusts the authenticated, deduplicated event and processes it under the normal database row lock without calling Zoom's API. It emits a critical log because the event cannot be checked for staleness. Other Zoom event types have their own request validation rules.

### Password updates and the refreshed join link

Zoom may return no response body after a meeting update, and a password change can refresh the meeting's `join_url`. The application therefore leaves a successful password update in `updating` with its encrypted operation payload and schedules recovery. Recovery reads the meeting from Zoom. If the requested password is present, it saves Zoom's full snapshot, including the current `join_url`, and clears the operation. If Zoom still reports the old password, recovery may retry the PATCH, but it waits for a later GET before completing locally. Repeated mismatches stop at the configured retry limit and leave the operation in `UpdateFailed` for manual review.

This path makes a lost update webhook recoverable. During the verification delay, local state can still contain the previous join link; persistent Zoom or worker failures can require manual review.

The ownership boundaries are:

- operation IDs identify the current local meeting operation;
- claim tokens and leases coordinate recovery workers;
- the inbox deduplicates and retries webhook delivery;
- row locks protect final meeting state transitions;
- recovery verifies Zoom when an API result is uncertain;
- provider timestamps order webhook events.

## Deduplication Mechanism

### Deterministic Fingerprint

The current Zoom integration identifies each webhook with a SHA256 fingerprint:

```
SHA256(signature + ":" + timestamp + ":" + raw_body)
```

This fingerprint is stored as `event_key` in the database. The database has a unique constraint on `(provider, event_key)` which prevents duplicate webhooks from being stored.

### Key Points

- **x-zm-request-id is NOT the unique identifier** - it's correlation data only
- **Same body with different timestamp = different webhook** (different signature)
- **Database constraint is authoritative** - cache or middleware cannot bypass it
- **Duplicate webhooks with same fingerprint return 200 with existing row** - completed rows are not processed again, while recoverable rows may be redispatched
- **At-least-once semantics** - webhooks may be processed multiple times after crashes/leases expire, business actions must tolerate repetition

## State Machine

### States

- **Received**: Webhook accepted, waiting for processing
- **Processing**: Worker has claimed the webhook (5-minute lease)
- **Completed**: Webhook processed successfully
- **Failed**: Webhook permanently failed after 5 attempts

### Transitions

```
Received → Processing (claim)
Processing → Completed (success)
Processing → Received (retry with backoff)
Processing → Failed (5th attempt)
```

## Processing Semantics

### At-Least-Once Guarantees

- **Committed webhooks remain recoverable** - stored in database before acknowledgment
- **Database failures are not acknowledged** - the request fails before durable acceptance and the provider may retry it
- **Workers can crash** - 5-minute lease allows recovery
- **Queue can fail** - webhooks remain in `received` state
- **Retries are tracked** - durable attempt count in database

### Claim Ownership

- **UUID claim tokens** - each processing attempt gets unique token
- **5-minute lease** - `claim_expires_at` marks when claim expires
- **Atomic claiming** - database `increment()` prevents race conditions
- **Token validation** - old tokens cannot update newer claims

### Zoom Retry Strategy

The following policy is the current Zoom inbox policy. A future provider may require a different policy, but it must still have one durable retry owner.

| Attempt | Backoff     | State    |
| ------- | ----------- | -------- |
| 1       | 15 seconds  | Received |
| 2       | 30 seconds  | Received |
| 3       | 60 seconds  | Received |
| 4       | 120 seconds | Received |
| 5       | Terminal    | Failed   |

## Recovery Mechanism

### Scheduler Command

**Command:** `webhooks:recover-pending --limit=100` (the command default; the production scheduler runs it without an explicit limit)

**Frequency:** Every minute

**What it does:**

1. Marks expired claims at 5+ attempts as Failed
2. Finds webhooks in `received` state with due backoff
3. Finds webhooks in `processing` state with expired leases
4. Dispatches `ProcessZoomWebhookInbox` jobs for each
5. Returns counts of dispatched vs failed

**Scheduler Configuration:**

```php
$schedule->command('webhooks:recover-pending')
    ->name('recover-pending-webhooks')
    ->onOneServer()
    ->withoutOverlapping(5)
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
```

The five-minute overlap expiry lets recovery resume after an abnormal scheduler exit. `onOneServer()` and overlap locks require a shared cache store across scheduler hosts; production uses shared Redis.

## Monitoring

### Key Metrics

- **Pending webhooks**: Count of `received` rows
- **Stuck webhooks**: Count of `processing` rows with expired leases
- **Failed webhooks**: Count of `failed` rows
- **Processing attempts**: Average attempts per webhook
- **Recovery success rate**: Dispatched vs failed from recovery command

### Terminal Failure Monitoring

Failed webhooks are marked with:

- `last_error_class`: Exception class name
- `last_error_code`: Exception code
- `failed_at`: Timestamp of terminal failure

Monitor these for patterns indicating systemic issues.

## Operational Procedures

### Handling Failed Webhooks

1. **Check error class/code**: Identify root cause
2. **Review logs**: Check for recurring patterns
3. **Fix underlying issue**: Update business logic if needed
4. **Manual retry (if applicable)**: Update row to `received` with null `available_at`

### Clearing Old Completed Rows

Completed rows remain in the database for audit purposes. No automatic pruning is configured, so define a retention policy and remove old rows when operational requirements call for it.

### Queue Failures

If queue dispatch fails:

- Webhook remains in `received` state
- Recovery command will redispatch it
- No manual intervention needed

## Security Considerations

### Sensitive Data Protection

- **Payloads are encrypted** in database using Laravel's encrypted cast
- **Exception messages are NOT logged** - only class and code
- **Raw signatures are NOT logged** - only identifiers
- **Claim tokens are hidden** from `toArray()`

### What to Avoid in Logs

❌ Exception messages
❌ Raw webhook payloads
❌ Signature values
❌ Zoom access tokens
❌ Meeting passwords
❌ Join URLs

✅ Webhook inbox ID
✅ Event type
✅ Request ID
✅ Exception class
✅ Exception code

## Troubleshooting

### Webhook Not Processing

1. **Check database**: Row exists in `webhook_inboxes`?
2. **Check state**: Is it `received` or `processing`?
3. **Check claim**: Is `claim_expires_at` in the future?
4. **Check attempts**: Is it at max (5)?
5. **Check queue**: Is `webhooks` queue running?

### Duplicate Processing

Duplicate processing is **expected** under at-least-once semantics:

- Worker crashes between business action success and inbox completion
- Lease expires, another worker reclaims and processes again
- This is intentional - durability over exact-once guarantees

Atomic claiming prevents **simultaneous** processing, not replay after crashes.

Business actions must tolerate repeated execution.

## Notification Side Effects

Meeting state transitions record notification intent with a pending timestamp in the same database update:

- `started_notification_pending_at`
- `ended_notification_pending_at`

Notification jobs set the corresponding sent timestamp and clear the pending timestamp only after delivery succeeds. This keeps notification recovery independent from the meeting's current status. A meeting can move from started to ended while the started notification is still pending.

The `meetings:check-unsent-notifications` command redispatches pending notification jobs after the recovery delay. Queue dispatch failure and notification job failure therefore leave a durable pending signal for later recovery.

Notification delivery remains at-least-once. A worker crash after external delivery but before the sent timestamp is saved can result in a duplicate delivery.

### High Failed Rate

If many webhooks fail:

1. **Check error patterns**: Same class? Same code?
2. **Check business logic**: Are actions throwing exceptions?
3. **Check external dependencies**: Zoom API, database connectivity
4. **Check worker resources**: Memory, timeout limits

## Performance

### Database Considerations

- **Indexes**: `(state, available_at)` and `(state, claim_expires_at)` for efficient recovery queries
- **Encryption**: Payload encryption adds CPU overhead but is necessary for security

### Queue Considerations

- **Single retry per job**: `tries = 1` in job
- **Retry happens at service level**: 5 attempts with backoff
- **Queue failure resilience**: Webhooks survive queue outages

## Testing

### Test Coverage

- **Acceptance**: Database uniqueness, queue failure resilience
- **Processing**: Atomic claiming, token safety, retry backoff
- **Recovery**: Scheduler dispatch, lease expiration
- **Actions**: Idempotency of business handlers

Every provider must additionally cover authentication failures, malformed payloads, duplicate delivery, acceptance failure, queue-dispatch failure, expired claims, retry exhaustion, and replay-safe business handling.

### Verification Commands

```bash
# Webhook acceptance and processing
php artisan test tests/Feature/Services/Webhooks/ZoomWebhookInboxServiceTest.php
php artisan test tests/Feature/Jobs/Webhooks/ProcessZoomWebhookInboxTest.php

# Endpoints and middleware
php artisan test tests/Feature/Api/Webhooks/Zoom/
php artisan test tests/Feature/Api/Middleware/Zoom/

# Scheduler
php artisan test tests/Unit/Console/KernelScheduleTest.php
```

## Adding Another Provider

Do not copy Zoom's verification or event parsing into shared classes. Follow the provider onboarding checklist, keep provider-specific code at the integration boundary, and reuse the inbox reliability behavior only where the provider's delivery semantics allow it.
