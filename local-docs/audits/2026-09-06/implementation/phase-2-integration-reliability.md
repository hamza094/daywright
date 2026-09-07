# Phase 2: Integration reliability

Release boundary: **before relying on integrations**.

Source: `local-docs/audits/2026-09-06/rest-production-audit.md`, priorities 3-5 and 7, and release-roadmap step 2. Finding descriptions, severity, and reproduction evidence live in [findings-reference.md](findings-reference.md).

## SWE 1.6 handoff

Implement this phase in the current Laravel repository. Preserve unrelated working-tree changes. Audit references identify symbols in the reviewed code; locate them again before editing. The audit requires durable acceptance/operation records, reconciliation, message recovery, and serialized state validation. Proposed filenames, schema details, test scenarios, and verification procedures below are implementation elaborations.

Prerequisites: phase 1 account/token boundaries, corrected idempotency identity, bounded lease decisions, dependency updates, and webhook retry protection. Reuse a durable inbox/operation implementation if phase 1 already introduced it. Otherwise replace the interim webhook reservation behavior in this phase.

Use isolated test data and provider doubles/sandboxes. Phase 4 will verify multiple processes with the intended database, Redis, and actual workers. Do not equate a synchronous fake with worker-crash evidence.

## P2.1 — Persist webhook acceptance and recover pending events

**Existing files to modify/review**

- `app/Http/Middleware/VerifyZoomWebhook.php:59` and `:175`.
- `app/Services/Webhooks/ZoomWebhookDispatcher.php:18`.
- `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`.
- `app/Jobs/Webhooks/Zoom/ZoomMeetingWebhookJob.php`.
- `app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php`.
- `app/Jobs/Webhooks/Zoom/MeetingEndedWebhook.php`.
- `app/Jobs/Webhooks/Zoom/UpdateMeetingWebhook.php`.
- `app/Jobs/Webhooks/Zoom/DeleteMeetingWebhook.php`.
- `app/Console/Kernel.php`.

**New (proposed) files**

- `app/Models/WebhookInbox.php`.
- `app/Jobs/Webhooks/ProcessWebhookInbox.php`.
- `app/Console/Commands/RecoverPendingWebhooks.php`.
- A timestamped migration under `database/migrations/` for inbox state and unique event identity.

**Implementation checklist — audit requirement**

- [ ] Persist a durable inbox row keyed by a unique provider event identifier.
- [ ] Track received/processing/completed state.
- [ ] Acknowledge only after durable acceptance; a cache reservation alone is insufficient.
- [ ] Process pending rows through workers and a reconciler, including rows whose initial queue dispatch failed.
- [ ] Keep signature/timestamp verification and the provider endpoint-validation challenge intact.

**Implementation elaboration**

- [ ] Verify which stable delivery/event identifier the provider supplies. If none is suitable, document a deterministic identity from verified event data that distinguishes separate events for the same meeting. Do not invent an assumed provider ID field.
- [ ] Add a database unique constraint on the chosen provider/event identity.
- [ ] Store the minimum payload needed for processing, with the phase 1 privacy/logging rules.
- [ ] Use atomic claim ownership and an expiry/attempt record so a dead worker cannot leave an event permanently processing.
- [ ] Acknowledge a duplicate only when its durable acceptance exists. Do not mark processing completed before business effects are committed.
- [ ] Make handlers safe for at-least-once processing; inbox uniqueness alone does not protect side effects after a worker crash.
- [ ] Register recovery scheduling in `app/Console/Kernel.php`, reusing the existing scheduler locking conventions.

**Tests to add/update**

- `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`.
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`.
- Existing handler tests under `tests/Feature/Api/Jobs/Webhooks/Zoom/`.
- **New (proposed):** `tests/Feature/Api/Webhooks/Zoom/WebhookInboxTest.php`.
- [ ] Database acceptance failure produces no successful acknowledgment.
- [ ] Successful inbox commit followed by queue failure leaves pending work that a reconciler processes.
- [ ] Repeated deliveries map to one durable event.
- [ ] A worker dying after claiming an event leaves recoverable work; expired ownership is reclaimed without an old worker overwriting the new result.
- [ ] Repeated processing of a committed event does not repeat its business effects.
- [ ] Retain phase 1's downstream-failure regression and endpoint-validation tests.

**Verification**

- [ ] Inspect database constraints and state transitions.
- [ ] Demonstrate pending-row recovery without depending on the original HTTP request or cache entry.
- [ ] Record the precise duplicate-delivery guarantee and any remaining external side-effect ambiguity.

## P2.2 — Recover ambiguous Zoom meeting creation

**Existing files**

- `app/Actions/Meetings/CreateProjectMeeting.php:40` and `:61`.
- `app/Actions/Meetings/Concerns/MeetingLockOperations.php`.
- `app/Models/Meeting.php`.
- `app/Enums/Meeting/MeetingSyncStatus.php`.
- `app/Services/Zoom/ZoomService.php`.
- `app/Services/Zoom/ZoomServiceFake.php`.
- `app/Interfaces/Zoom.php`.
- `app/Http/Integrations/Zoom/Requests/CreateMeeting.php`.
- `app/Console/Kernel.php`.

**New (proposed) files**

- `app/Models/IntegrationOperation.php`, shared with P2.3 if the domains fit.
- `app/Jobs/Meetings/CreateRemoteMeeting.php`.
- `app/Console/Commands/ReconcileMeetingOperations.php`.
- A timestamped migration under `database/migrations/` for durable operation identity, state, ownership, and provider references.

**Implementation checklist — audit requirement**

- [ ] Persist an operation/outbox record in the same transaction as the pending business change.
- [ ] Perform the provider call outside retryable database transactions.
- [ ] Persist provider identifiers and explicit unknown outcomes.
- [ ] Reconcile pending/unknown operations instead of blindly repeating meeting creation.
- [ ] Preserve a provider-supported idempotency reference wherever available.

**Implementation elaboration**

- [ ] Associate a stable operation identifier with the local meeting and request identity; enforce uniqueness in the database.
- [ ] Separate definite provider rejection from timeout/connection-loss outcomes where remote success is uncertain.
- [ ] Verify the actual provider's idempotency and lookup capabilities before choosing a reconciliation algorithm.
- [ ] When remote success cannot be determined automatically, retain an explicit unresolved state and an operator procedure. Do not declare exactly-once behavior unsupported by the provider.
- [ ] Make operation claim and local finalization repeatable, with finite retry/backoff behavior and useful sanitized context.
- [ ] Document API behavior if creation becomes asynchronous; synchronize resources/controllers/docs in P3.6.

**Tests to add/update**

- `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`.
- `tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php`.
- **New (proposed):** `tests/Feature/Api/V1/Meetings/MeetingRecoveryTest.php`.
- [ ] Pending meeting and operation record commit or roll back together.
- [ ] Provider success followed by failed local finalization is reconciled to the existing remote meeting.
- [ ] Timeout becomes unknown until evidence resolves it.
- [ ] Definite rejection follows the documented failed/retryable behavior.
- [ ] Duplicate requests and repeated reconciliation cannot create an extra meeting within the supported guarantee.
- [ ] Preserve encrypted/hidden meeting credentials and current access checks.

**Verification**

- [ ] Inspect provider calls to ensure retryable local transactions do not contain remote mutation.
- [ ] Record how each crash window is detected and recovered.
- [ ] Add the reconciler to scheduling and verify it can find previously stranded pending records.

## P2.3 — Move Paddle mutations outside retryable database transactions

**Existing files**

- `app/Services/Paddle/SubscriptionService.php:175`, including callers of `executeSerially()`.
- `app/Services/Paddle/SubscriptionServiceFake.php`.
- `app/Http/Controllers/Api/V1/SubscriptionController.php`.
- `app/Listeners/PaddleEventListener.php`.
- `routes/api/v1/users.php`.
- `app/Console/Kernel.php`.

**New (proposed) placement**

- Reuse `app/Models/IntegrationOperation.php` from P2.2 or introduce a domain-specific subscription operation model if necessary.
- `app/Jobs/Subscriptions/ProcessSubscriptionOperation.php`.
- `app/Console/Commands/ReconcileSubscriptionOperations.php`.
- Extend the selected timestamped operation migration or add a dedicated one.

**Implementation checklist — audit requirement**

- [ ] Persist billing operation intent under the appropriate local transaction/uniqueness rules.
- [ ] Execute subscribe/swap/cancel provider calls outside database transactions that automatically retry.
- [ ] Preserve required serialization while moving the network call; simply removing the row lock is insufficient.
- [ ] Persist provider references/outcomes and reconcile pending/unknown operations.
- [ ] Reuse provider-supported idempotency references where actually supported.

**Implementation elaboration**

- [ ] Define which concurrent subscription changes are compatible, rejected, or queued.
- [ ] Correlate provider webhook events with local operation state without persisting the full webhook payload as audit metadata.
- [ ] Reconcile against authoritative provider state after an ambiguous response or local finalization failure.
- [ ] Make duplicate/out-of-order callbacks unable to regress a confirmed newer state.
- [ ] Keep first-party/session and subscription permissions from phase 1 intact.

**Tests to add/update**

- `tests/Unit/Services/Paddle/SubscriptionServiceTest.php`.
- `tests/Unit/Services/Paddle/SubscriptionServiceFakeTest.php`.
- `tests/Feature/Api/V1/Subscriptions/SubscriptionManagementTest.php`.
- `tests/Feature/Api/Webhooks/Paddle/PaddleWebhookTest.php`.
- `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`.
- **New (proposed):** `tests/Feature/Api/V1/Subscriptions/SubscriptionRecoveryTest.php`.
- [ ] A retried local transaction cannot repeat `swapAndInvoice()` or another provider mutation.
- [ ] Concurrent incompatible operations follow the selected serialization rule.
- [ ] Provider success followed by local failure converges through reconciliation.
- [ ] Duplicate callbacks and request retries do not create duplicate billing effects within the provider-supported guarantee.
- [ ] Provider rejection and unknown outcome have distinct state and API behavior.

**Verification**

- [ ] Review every remote call within `SubscriptionService`, not only `swapAndInvoice()`.
- [ ] Verify supported provider behavior in a test/sandbox environment before claiming recovery.

## P2.4 — Recover message claims and failed recipients

**Existing files**

- `app/Actions/Project/DispatchProjectMessageAction.php:24`, `:84`, and `:111`.
- `app/Models/Message.php:51`.
- `app/Actions/Project/CreateProjectMessageAction.php`.
- `app/Actions/Project/ScheduleProjectMessageAction.php`.
- `app/Console/Commands/ScheduledMessages.php`.
- `app/Jobs/MailMessage.php` and `app/Jobs/SmsMessage.php`.
- `app/Console/Kernel.php`.

**New (proposed) files**

- `app/Models/MessageDelivery.php` for recipient/channel outcome where needed.
- `app/Console/Commands/RecoverMessageDispatches.php`.
- Timestamped migrations under `database/migrations/` for outbox intent, claim ownership/expiry, and delivery state. Reuse a suitable outbox from P2.1-P2.3 rather than duplicating mechanisms.

**Implementation checklist — audit requirement**

- [ ] Persist dispatch intent/outbox state in the business transaction.
- [ ] Give claims timestamps/leases and recover abandoned claims.
- [ ] Make both immediate dispatch and scheduled selection eligible to recover abandoned work.
- [ ] Move terminal success/failure cleanup into `finally()` or explicit per-recipient completion handling.
- [ ] Retry failed recipients without resending to confirmed successful recipients.

**Implementation elaboration**

- [ ] Keep claim identity separate from a real batch identifier, or define an explicit typed state if sharing storage remains appropriate.
- [ ] A stale batch callback must not clear a newer attempt's ownership.
- [ ] Define delivered/partially delivered/unknown states from actual recipient outcomes.
- [ ] Account for an external mail/SMS send succeeding before local delivery state is saved; preserve provider idempotency/reference where available and document unresolved ambiguity.
- [ ] Remove the assumption that `DB::afterCommit()` alone supplies durable dispatch.

**Tests to add/update**

- `tests/Feature/Api/V1/Messages/MessageTest.php`.
- `tests/Feature/Api/V1/JobsTest.php`.
- **New (proposed):** `tests/Feature/Api/V1/Messages/MessageDispatchRecoveryTest.php`.
- [ ] A crash after claim commit but before dispatch leaves recoverable intent.
- [ ] An expired claim can be taken by a new attempt; a live claim cannot.
- [ ] Terminal batch failure actually executes cleanup/recovery.
- [ ] Mixed recipient results retry only failed/eligible deliveries.
- [ ] Re-running the scheduler does not duplicate confirmed deliveries.
- [ ] A callback from an older batch cannot mark the latest attempt completed.

**Verification**

- [ ] Exercise terminal failure using the real batch lifecycle in phase 4, not only hand-invoked success callbacks.
- [ ] Confirm abandoned messages are included by the recovery query.
- [ ] Document operator handling for unresolved provider outcomes.

## P2.5 — Serialize task/project transitions and detect lost edits

**Existing files**

- `app/Traits/HasStateMachine.php:26`.
- `app/Services/Task/TaskService.php:85`.
- `app/Services/Project/ProjectService.php:114`.
- Supporting models: `app/Models/Task.php` and `app/Models/Project.php`.
- Request contracts: `app/Http/Requests/Api/V1/Task/TaskUpdateRequest.php` and `app/Http/Requests/Api/V1/Project/ProjectUpdateRequest.php`.

**Implementation checklist — audit requirement**

- [ ] Re-fetch and lock the affected row inside the transaction before transition validation and mutation.
- [ ] Apply all related updates to that locked model; do not mix stale route-bound attributes back into the write.
- [ ] Apply the same approach to project transitions.
- [ ] For collaborative editing, introduce an explicit version and conditional update/If-Match contract to detect lost updates.

Verbatim audit pattern; this is not a complete service implementation:

```php
return DB::transaction(function () use ($task, $data) {
    $lockedTask = Task::query()->whereKey($task->getKey())
        ->lockForUpdate()->firstOrFail();
    // Validate transition and apply every update to $lockedTask.
});
```

**Implementation elaboration**

- [ ] Inspect every transition caller for equivalent stale-state exposure; avoid exposing an unsafe generic transition path to other callers.
- [ ] Keep lock acquisition order consistent for operations touching multiple models.
- [ ] Define whether version/precondition checking applies to all collaborative updates or a documented subset.
- [ ] Use a database conditional write or lock+version check; a separate unlocked version read is insufficient.
- [ ] Surface conflicts through the existing error format and synchronize request/resource/docs changes in P3.6.
- [ ] Keep same-state setter semantics aligned with P3.1 without allowing forbidden terminal transitions.

**New (proposed) schema work**

- A timestamped migration under `database/migrations/` adding version columns if version-based editing is selected.

**Tests to add/update**

- `tests/Unit/Models/TaskStateMachineTest.php`.
- `tests/Unit/Models/ProjectStateMachineTest.php`.
- `tests/Feature/Api/V1/Tasks/TaskTest.php`.
- `tests/Feature/Api/V1/StageTest.php`.
- `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php`.
- [ ] Port the two-snapshot probe at line 72: the cancelled task remains terminal.
- [ ] Repeat the stale snapshot scenario for projects.
- [ ] A conflict rolls back all coupled business changes and avoids duplicate side effects.
- [ ] Matching version/precondition succeeds; stale precondition is rejected according to the selected contract.
- [ ] Phase 4 adds genuinely simultaneous database transactions.

## Phase verification and acceptance

Run relevant targeted tests as each subsystem changes, then the full suite and configured analysis:

```sh
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Webhooks
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Jobs/Webhooks/Zoom
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Meetings
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Subscriptions
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Messages
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Unit/Models
composer test
composer stan
php artisan schedule:list
```

- [ ] Durable records survive a failed initial dispatch and identify how pending work is recovered.
- [ ] Remote mutations run outside automatically retryable database transactions.
- [ ] Unknown provider outcomes remain identifiable until reconciled or explicitly escalated.
- [ ] Message claims and recipient failures have a tested recovery path.
- [ ] Task/project transition checks use current serialized database state.
- [ ] New migrations, schedules, configuration, and API behavior are documented.
- [ ] Record changed files, test results, provider guarantees/limitations, and outstanding phase 4 process-level verification.

Passing this phase's deterministic tests establishes implementation behavior under the tested scenarios. Real interruption/concurrency evidence remains a phase 4 gate.
