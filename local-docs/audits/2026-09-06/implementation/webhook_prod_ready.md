# P2.1 — Durable Zoom Webhook Inbox

## Summary

Implement one phase per SWE 1.6 prompt. Keep each phase green before continuing.

The final design adds only one model, enum, service, job, recovery command, migration, and factory. It preserves the existing routes, signatures, DTOs, business actions, `webhooks` queue, and response body.

Public behavior changes only for duplicates: every durably accepted delivery returns the existing `200 {"message":"Webhook accepted."}` instead of the interim `202` response.

## Phase 0 — Baseline and safety contract

- Confirm the current Zoom middleware, endpoint, job, and action tests pass.
- Record the existing baseline of 18 tests and 54 assertions.
- Confirm the worktree is clean or preserve unrelated changes.
- Lock these invariants:
  - Signature and timestamp verification remain unchanged.
  - Endpoint validation never creates an inbox row.
  - Database acceptance must complete before acknowledgment.
  - Queue failure after database acceptance must not lose the event.
  - Processing is at-least-once; existing actions must tolerate repetition.
  - SQLite and MySQL remain supported.

Gate: no code changes and the focused baseline passes.

## Phase 1 — Add the inbox data foundation

Add:

- `WebhookInboxState` string enum with `received`, `processing`, `completed`, and `failed`.
- `WebhookInbox` model using `$guarded = []`.
- Migration and test factory.

Use this schema:

```text
id
provider                    varchar(32)
event_key                   char(64)
event_type                  varchar(100)
provider_request_id         varchar(255)
provider_occurred_at        unsigned bigint nullable
payload                     long text
state                       varchar(20), default received
attempts                    unsigned tiny integer, default 0
available_at                timestamp nullable
claim_token                 char(36) nullable
claimed_at                  timestamp nullable
claim_expires_at            timestamp nullable
completed_at                timestamp nullable
failed_at                   timestamp nullable
last_error_class            varchar(255) nullable
last_error_code             varchar(64) nullable
created_at
updated_at
```

Constraints:

- Unique `(provider, event_key)`.
- Index `(state, available_at)`.
- Index `(state, claim_expires_at)`.

Model behavior:

- Cast `payload` as `encrypted:array`.
- Cast state to `WebhookInboxState`.
- Cast all lifecycle timestamps to datetime.
- Hide `payload` and `claim_token`.
- Do not add business transition methods to the model.

Add `toArray()` and `fromArray()` transformation methods to the four existing Zoom webhook DTOs. Persist only their normalized fields, not complete provider requests.

Tests:

- Schema constraints and casts work.
- Duplicate provider/event key is rejected.
- The same key can exist for a different provider.
- Raw database payload does not expose recognizable password, join URL, or start URL values.
- DTO serialization round-trips without losing types.

Gate: migration and focused model/DTO tests pass on SQLite.

## Phase 2 — Implement inbox acceptance and processing

Create one `final readonly ZoomWebhookInboxService`. Do not add repositories, interfaces, query builders, or separate inbox actions.

The service owns:

```php
accept(
    string $eventKey,
    string $eventType,
    string $requestId,
    ?int $occurredAt,
    MeetingUpdatedWebhookData
        | MeetingStartedWebhookData
        | MeetingEndedWebhookData
        | MeetingDeletedWebhookData $data,
): WebhookInbox

process(int $inboxId): void

dispatchRecoverable(int $limit): array
```

Acceptance behavior:

- Use `createOrFirst()` with `(provider, event_key)`.
- Only the expected unique collision counts as a duplicate.
- Dispatch after the insert transaction completes.
- Dispatch new or currently recoverable rows.
- Never redispatch completed, failed, or live-claimed rows.
- If queue dispatch fails, call `report()`, log only safe identifiers, retain `received`, and return the durable row.

Create `ProcessWebhookInbox`:

- Accept only an integer inbox ID.
- Use standard queue traits.
- Queue: `webhooks`.
- `$tries = 1`.
- `$timeout = 60`.
- `$failOnTimeout = true`.
- Inject the service into `handle()` and call `process($id)`.
- `failed()` reports/logs safe row identity but does not release ownership without a claim token.

Processing behavior:

1. Atomically claim a `received` due row or expired `processing` row.
2. Generate a new UUID claim token.
3. Increment durable attempts.
4. Set a five-minute claim lease.
5. Reconstruct the DTO from the encrypted payload.
6. Call the matching existing `HandleMeeting*Webhook` action directly.
7. Mark completed only through `WHERE id/state/claim_token`.
8. On an ordinary exception, report it and conditionally:
   - return to `received` with backoff when attempts remain;
   - move to `failed` on attempt five.
9. Clear previous error fields after successful completion.

Backoff by durable attempt:

```text
1 → 15 seconds
2 → 30 seconds
3 → 60 seconds
4 → 120 seconds
5 → terminal failure
```

Do not enqueue the legacy webhook jobs from `ProcessWebhookInbox`.

Tests:

- Acceptance creates exactly one durable row.
- Queue failure leaves recoverable accepted work.
- Only one competing worker obtains a claim.
- A live claim cannot be reclaimed.
- An expired claim receives a new token.
- An old token cannot complete or fail a newer claim.
- Successful processing marks completed.
- Exceptions schedule retry and record only class/code.
- Attempt five becomes failed.
- Completed and failed rows are no-ops.

Gate: service and job lifecycle tests pass.

## Phase 3 — Verify at-least-once business safety

Exercise every existing `HandleMeeting*Webhook` action through the inbox service.

Required guarantees:

- Repeating an update does not create additional effects.
- Repeating deletion leaves the meeting deleted.
- Repeating start does not dispatch another start notification.
- Repeating ended does not dispatch another ended notification.
- An ended meeting cannot be returned to started.
- Missing meetings remain safe no-ops with existing operational handling.
- A simulated crash after the business action but before inbox completion can be reclaimed without duplicating effects.

Only modify existing actions when one of these tests fails. Keep fixes as conditional database writes inside the existing action; do not introduce another abstraction.

Gate: all repeated-processing and existing webhook action tests pass.

## Phase 4 — Add automatic recovery

Create:

```text
webhooks:recover-pending {--limit=100}
```

Behavior:

- Select due `received` rows and expired `processing` rows.
- Dispatch `ProcessWebhookInbox` by integer ID.
- Do not claim or reset rows in the command.
- Protect each dispatch with `try/catch`; one corrupt row or queue error must not stop the loop.
- Report unexpected exceptions.
- Output selected, dispatched, skipped, and failed counts.
- Return failure when any dispatch fails, while leaving rows recoverable.
- Never log payloads or successful row details.

Schedule every minute with:

```text
name: recover-pending-webhooks
onOneServer()
withoutOverlapping()
appendOutputTo(scheduler log)
```

Tests:

- Due received rows are dispatched.
- Expired processing rows are dispatched.
- Live claims, delayed retries, completed rows, and failed rows are ignored.
- One dispatch failure does not prevent later rows from being attempted.
- Scheduler registration has the required frequency and locks.

Gate: command tests pass and `php artisan schedule:list` shows the recovery command.

## Phase 5 — Cut ingress over to durable acceptance

Update `VerifyZoomWebhook`:

- Preserve required headers, signature verification, timestamp tolerance, and endpoint validation.
- Remove cache replay reservation and cache cleanup.
- Remove idempotency-header mapping.
- After successful verification, calculate:

```text
sha256(signature + ":" + request timestamp + ":" + exact raw body)
```

- Store the result as a request attribute for the controller.
- Do not create a fingerprint for endpoint-validation challenges.

Update the controller:

- Use method injection for `ZoomWebhookInboxService`.
- Pass the fingerprint, validated event name, request ID, optional `event_ts`, and typed webhook DTO.
- Call only the inbox service.
- Return the existing exact `200` response after durable acceptance.
- Allow database failures to use the standard sanitized `500` handling.
- Do not return an error when only the initial queue dispatch fails.

Update routes:

- Keep `VerifyZoomWebhook` and `throttle:webhook-ingress`.
- Remove the general `Idempotent` middleware from Zoom webhook routes.
- Database uniqueness becomes the single authoritative deduplication mechanism.

Remove after cutover:

- `ZoomWebhookDispatcher`.
- Four legacy queued webhook wrappers.
- Their abstract webhook job base and queue-only concern if unused.
- Cache replay tests and idempotency-header mapping tests.

Migrate useful legacy tests to the inbox endpoint/processor tests.

Endpoint tests:

- Valid update, delete, start, and ended deliveries persist and dispatch.
- Same body and request ID produces one inbox row.
- Same body with a different request ID still produces one inbox row.
- Different signed bodies produce separate rows.
- Duplicate completed delivery returns the same `200` response without processing again.
- Database failure returns sanitized `500` with no acknowledgment.
- Queue failure after commit returns `200` and leaves `received`.
- Invalid signature, stale timestamp, missing headers, throttling, and endpoint validation retain current behavior.

Gate: all Zoom middleware, endpoint, inbox, and handler tests pass.

## Phase 6 — Documentation and release verification

Update operational documentation to describe:

- Database-backed acceptance and deduplication.
- The deterministic Zoom delivery fingerprint.
- Five-minute claim leases and five durable attempts.
- Automatic one-minute recovery.
- Terminal failed-row monitoring.
- At-least-once rather than exactly-once processing.
- Remaining real-process crash verification deferred to Phase 4 production-readiness work.

Update the API overview so it no longer claims Zoom request IDs are the authoritative idempotency mechanism. Regenerate `api.json` only if the tracked overview changes its generated content.

Run:

```bash
php artisan test --compact tests/Feature/Api/Middleware/Zoom
php artisan test --compact tests/Feature/Api/Webhooks/Zoom
php artisan test --compact tests/Feature/Api/Jobs/Webhooks/Zoom
composer test
composer stan
composer pint:test
composer rector:test
php artisan schedule:list
php artisan scramble:analyze
```

Verification requirements:

- Full SQLite CI passes.
- Full MySQL 8.4 CI passes.
- Migration uses no database-specific enum or partial-index behavior.
- No raw provider payload, credential, signature, exception message, or SQL binding appears in logs.
- No unrelated public route or response-body changes are introduced.

## Assumptions and exclusions

- Implement exactly one phase per SWE 1.6 prompt.
- `x-zm-request-id` remains required correlation data but is not the durable identity.
- `event_ts` remains optional to preserve the current request contract.
- The existing `webhooks` queue and worker configuration remain unchanged.
- No repository, generic multi-provider framework, administrative inbox API, dashboard, manual retry command, pruning command, or second outbox table is included in P2.1.
- Completed-row retention can be addressed later after an operational retention policy is chosen.
