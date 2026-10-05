I reviewed all 16 suggestions against the current code and focused tests.

Result: 13 are confirmed defects, and 3 are valid hardening recommendations but not confirmed production bugs. I made no code changes.

Focused tests currently pass: 93 tests, 290 assertions. These tests do not cover all reported race conditions.

## Confirmed defects

### 1. Expired Zoom recovery claim becomes permanently stuck — Valid, P1

`RecoverAmbiguousZoomMeeting::claim()` changes an expired `Creating` meeting to `CreateUnknown`, but leaves `sync_available_at` as `NULL`.

After the worker dies:

- `Creating` scope no longer matches.
- `CreateUnknownDueAt()` excludes `NULL`.
- The claim token prevents manual resolution.

Fix in [RecoverAmbiguousZoomMeeting.php](C:/Users/Hamza/daywright/app/Actions/Meetings/RecoverAmbiguousZoomMeeting.php:103):

- Set `sync_available_at` to `now()` when claiming.
- Preserve `NULL` only when deliberately moving to manual review.
- Add a test for a crashed worker followed by successful reclaim.

### 2. Late Zoom creation callbacks can throw invalid state transitions — Valid, P2

The webhook can move `Creating → CreateUnknown` while the original request is still running. The original request then attempts:

- `CreateUnknown → CreateUnknown`
- `Active → Active`

Both are rejected by the state machine.

Fix [CreateProjectMeeting.php](C:/Users/Hamza/daywright/app/Actions/Meetings/CreateProjectMeeting.php:93):

- Lock the meeting row.
- Treat an already-active matching result as idempotent.
- Treat an already-unknown result as handled.
- Never clear or overwrite a newer recovery claim.
- Reject conflicting remote IDs as manual review.

Add tests covering:

- Webhook first, then timeout.
- Recovery first, then original success response.
- Matching and conflicting remote IDs.

### 3. Subscription recovery can update the wrong local subscription — Valid, P1

Both recovery paths verify Paddle using the operation’s recorded Paddle ID, but then fall back to the user’s current subscription.

Because `subscription_id` uses `nullOnDelete`, a replacement subscription with a different Paddle ID can receive the old operation’s plan change or cancellation.

Affected files:

- [RecoverSubscriptionOperation.php](C:/Users/Hamza/daywright/app/Actions/Subscription/RecoverSubscriptionOperation.php:136)
- [ResolveSubscriptionOperation.php](C:/Users/Hamza/daywright/app/Actions/Subscription/ResolveSubscriptionOperation.php:61)

Fix:

- Select the local subscription inside the transaction.
- Lock it with `lockForUpdate()`.
- Require its `paddle_id` to equal `operation.paddle_subscription_id`.
- Move the operation to manual review when no matching subscription exists.
- Do not update any replacement subscription.

Add the replacement-subscription regression test to both recovery and manual-resolution paths.

### 4. Paddle service validates stale subscription state — Valid, P1

`swap()` and `cancel()` validate the original `User` before acquiring the database lock. The transaction locks a fresh user row but discards it. The provider call also selects the subscription from the stale user instance.

This allows a second request to submit a redundant provider mutation after the first request has completed.

Fix [SubscriptionService.php](C:/Users/Hamza/daywright/app/Services/Paddle/SubscriptionService.php:151):

- Acquire the user lock before final business-state validation.
- Reload the subscription after the lock.
- Validate the fresh plan/status/trial state.
- Return the fresh subscription together with the operation context.
- Use that exact subscription for the provider call.

Add a test using two user instances loaded before the first plan change.

### 5. Trial swap creates a stuck unknown operation — Valid, P1

`isBillingSubscribed()` deliberately accepts `trialing` subscriptions, but Cashier’s `guardAgainstUpdates()` rejects swaps during trial before making an HTTP request.

The current code has already created an operation and set `provider_attempted_at`, then converts the local rejection into `Unknown`. Recovery cannot prove a request that was never sent, and the operation can block future billing actions.

Fix:

- Perform Cashier-compatible preflight validation before creating a provider-attempted operation.
- Convert known local restrictions into a definite `SubscriptionException`.
- Reserve `Unknown` only for uncertain provider execution.
- Cover trial, paused, grace-period, and past-due restrictions if the same Cashier guard applies.

Done when a trial swap makes no provider call and leaves no active operation.

### 6. Same-stage postponed reason changes are discarded — Valid, P2

[ProjectService.php](C:/Users/Hamza/daywright/app/Services/Project/ProjectService.php:171) returns immediately when the stage is unchanged, so a new `postponed_reason` is ignored and the version is not incremented.

Fix:

- Calculate the desired postponed reason.
- Return as a no-op only when both stage and reason are unchanged.
- Update only the reason and increment `version` when the stage is unchanged.
- Do not run stage-transition side effects for a reason-only edit unless the existing product contract requires notification.

Add a service/API test asserting the new reason and incremented version.

### 7. Recovery command limits accept unbounded input — Valid, P2

These commands cast input directly to integers:

- `meetings:recover-ambiguous`
- `webhooks:recover-pending`
- `subscriptions:recover-operations`

Laravel ignores negative limits, so `--limit=-1` can select all eligible rows. Non-numeric input becomes zero and reports success without processing anything.

Fix:

- Validate the raw option before querying or dispatching.
- Require a positive decimal integer.
- Add documented maximum limits.
- Return a failure exit code before calling recovery services.

Test negative, zero, nonnumeric, and oversized values, including “no provider/recovery call occurred.”

### 8. Manual meeting IDs are coerced unsafely — Valid, P2

`(int) '123abc'` becomes `123`, and `(int) '123.9'` also becomes `123`.

Affected file: [ResolveAmbiguousZoomMeeting.php](C:/Users/Hamza/daywright/app/Console/Commands/ResolveAmbiguousZoomMeeting.php:31)

Fix:

- Validate the original argument first.
- Accept only a positive integer within the PHP/database range.
- Reject prefixes, decimals, signs, zero, and overflow.
- Perform no lookup when validation fails.

### 9. Provider recovery blocks the scheduler process — Valid, P2 operational issue

The meeting recovery command can perform 25 sequential Zoom calls with 30-second timeouts. It runs before subscription recovery, so a provider outage can delay all later scheduled work.

Fix:

- Use `runInBackground()` for provider-backed scheduled commands, or dispatch bounded recovery jobs.
- Preserve `onOneServer()`, `withoutOverlapping()`, and row-level claim checks.
- Add a schedule test proving background execution.
- Verify behavior on the actual production scheduler platform.

### 10. Password reset tokens are readable in queue payloads — Valid, P1

[QueuedPasswordResetJob.php](C:/Users/Hamza/daywright/app/Jobs/QueuedPasswordResetJob.php:17) stores the raw reset token and does not implement `ShouldBeEncrypted`.

A queue or failed-job database reader could retrieve a still-valid token.

Fix:

- Implement `Illuminate\Contracts\Queue\ShouldBeEncrypted`.
- Verify the raw token is absent from the serialized database queue payload.
- Verify normal worker decryption and delivery.
- Review and drain existing unencrypted queued/failed reset jobs before deployment.
- Do not change reset token generation or the public reset API.

### 11. Meeting notifications are marked sent before delivery — Valid, P2

`MeetingStarted` and `MeetingEnded` implement `ShouldQueue`. Therefore, `Notification::send()` queues downstream notification work and returns before mail/database/broadcast delivery completes.

The parent job then sets `*_notification_sent_at` and clears pending state. A failed downstream notification is therefore invisible to `CheckUnsentMeetingNotifications`.

Existing tests use `Notification::fake()`, so they do not cover this sequence.

Fix as a dedicated delivery-state change:

- Track delivery per meeting event, recipient, and channel.
- Distinguish “dispatch queued” from “delivery completed.”
- Mark the meeting sent only when all required deliveries succeed.
- Retry only failed or incomplete delivery units.
- Add real queue/channel failure tests; retain notification fakes only for unit-level tests.

This requires a migration and should be implemented as its own larger ticket.

### 12. SMS failures can count as successful batch jobs — Valid, P2

`VonageSmsService::send()` returns normally for:

- Missing mobile recipient.
- Nonzero Vonage status.

`SmsMessage` ignores the return value. Laravel therefore treats the job as successful, and the message batch can set `delivered = true`.

Fix:

- Introduce typed SMS outcomes or typed exceptions.
- Retry transient provider failures.
- Record permanent rejection and missing-recipient outcomes separately.
- Ensure unsuccessful SMS never causes `delivered = true`.

Add tests for nonzero Vonage status and missing mobile numbers.

### 13. Default exception reporting can bypass sanitization — Valid, P2 security risk

The dedicated Zoom log is sanitized, but infrastructure Zoom exceptions continue to `parent::report($e)`.

Laravel adds the raw exception object and previous-exception chain to the default log context. `ScrubSensitiveData` sanitizes arrays but not exception objects, and the default message is also not normalized.

Affected files:

- [Handler.php](C:/Users/Hamza/daywright/app/Exceptions/Handler.php:67)
- [ZoomMeetingCreationUnknownException.php](C:/Users/Hamza/daywright/app/Exceptions/Integrations/Zoom/ZoomMeetingCreationUnknownException.php:12)
- [ScrubSensitiveData.php](C:/Users/Hamza/daywright/app/Logging/ScrubSensitiveData.php:36)

Fix:

- Add a controlled provider-exception reporting path.
- Send only safe class, status, code, correlation ID, and fixed diagnostic fields.
- Never forward the original exception or previous chain to logs/Bugsnag.
- Add a sentinel-secret test covering both daily logs and error reporting.

This is a reporting-path vulnerability, not proof that a secret has already leaked.

## Additional hardening recommendations

These are reasonable but not confirmed defects:

14. Allowlist public Zoom metadata. `ZoomException::meta()` currently spreads all context into API responses. Expose only explicitly approved fields such as provider, reason, and validated retry seconds.

15. Filter throttle response headers. The throttle handler forwards all exception headers directly. Reuse the validated header allowlist while preserving required rate-limit headers.

16. Avoid error-level reporting for expected edit conflicts. `EditConflictException` represents normal optimistic-concurrency behavior and can be excluded from default error reporting while retaining a metric or lower-severity event.

## SWE 1.6 implementation order

Use one ticket per run:

1. Zoom expired-claim recovery.
2. Zoom late creation callback handling.
3. Paddle subscription identity validation in recovery/manual resolution.
4. Fresh-state Paddle service locking.
5. Trial/preflight Paddle validation.
6. Same-stage postponed-reason update.
7. Recovery command input validation.
8. Manual meeting-ID validation.
9. Background provider recovery scheduling.
10. Encrypted password-reset queue job.
11. SMS typed outcomes and delivery failure recording.
12. Meeting notification delivery ledger.
13. Controlled provider exception reporting.
14. Optional hardening ticket for metadata, throttle headers, and edit-conflict reporting.

For every ticket, require:

- Exact starting files.
- Focused regression tests first or alongside the change.
- No changes to unrelated frontend/OpenAPI files.
- `git diff --check`.
- Focused PHPUnit tests.
- `composer stan` and `composer pint:test` where applicable.
- A final note distinguishing local tests from real MySQL, Redis, worker, scheduler, and provider-sandbox evidence.

The existing modified files in the worktree were preserved and not included in this review’s changes.
