# CodeRabbit Defect Status — Verified Against Code

> Verified against actual source code on 2026-10-12.  
> Source: [coderabbit_review.md](coderabbit_review.md)

---

## ✅ FIXED — Item 1: Expired Zoom recovery claim becomes permanently stuck (P1)

Code confirms [`RecoverAmbiguousZoomMeeting::claimValues()`](app/Actions/Meetings/RecoverAmbiguousZoomMeeting.php#L103-L111) sets `'sync_available_at' => $now` during the claim, so the `CreateUnknownDueAt()` exclusion no longer traps claimed meetings if a recovery worker crashes. The separate creation recovery (`meetings:recover-ambiguous`) and operation recovery (`meetings:recover-pending`) serve distinct domain purposes and both properly populate `sync_available_at`.

---

## ✅ FIXED — Item 6: Same-stage postponed reason changes discarded (P2)

[ProjectService.php](app/Services/Project/ProjectService.php#L170-L193) was updated:

- Checks both stage and `postponed_reason` before considering an update a no-op.
- When the stage is unchanged (`Postponed`) but the reason differs, updates `postponed_reason` and increments `version`.
- Skips stage-transition notification side effects for reason-only updates.
- Verified with unit tests in `tests/Unit/Services/Project/ProjectServiceTest.php`.

---

## ⚠️ PARTIALLY FIXED — Item 7: Recovery command limits accept unbounded input (P2)

| Command                                                                                                                          | Status                                           |
| -------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| `meetings:recover-pending` ([RecoverPendingZoomOperations.php](app/Console/Commands/RecoverPendingZoomOperations.php))           | ✅ Fixed — has `MAX_LIMIT = 100`, rejects `<= 0` |
| `meetings:recover-ambiguous` ([RecoverAmbiguousZoomMeetings.php](app/Console/Commands/RecoverAmbiguousZoomMeetings.php))         | ❌ Still uses raw `(int) $this->option('limit')` |
| `webhooks:recover-pending` ([RecoverPendingWebhooks.php](app/Console/Commands/RecoverPendingWebhooks.php))                       | ❌ Still uses raw `(int) $this->option('limit')` |
| `subscriptions:recover-operations` ([RecoverSubscriptionOperations.php](app/Console/Commands/RecoverSubscriptionOperations.php)) | ❌ Still uses raw `(int) $this->option('limit')` |

The remaining three scheduled commands still require limit validation.

---

## ✅ FIXED — Item 8: Manual meeting IDs coerced unsafely (P2)

[ResolveAmbiguousZoomMeeting.php](app/Console/Commands/ResolveAmbiguousZoomMeeting.php) was updated:

- Validates that `meeting_id` matches `/^[1-9]\d*$/`, rejecting all zero representations (`0`, `00`), prefixes, decimals, and leading zeros.
- Validates that `meeting_id` does not overflow 64-bit integers using `filter_var(..., FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]) !== false`.
- Validates optional `--zoom-meeting-id` with `/^[1-9]\d*$/` as a positive integer string.
- Returns `self::FAILURE` immediately on invalid input, ensuring no `Meeting::find()` lookup occurs.
- Verified with 11 validation tests in `tests/Feature/Console/Meetings/ResolveAmbiguousZoomMeetingTest.php`.

---

## ❌ OPEN — Item 9: Provider recovery blocks the scheduler process (P2)

No `runInBackground()` calls found in `Kernel.php` for provider-backed recovery commands. The scheduler still runs recovery commands synchronously, meaning 25 sequential Zoom calls (30s timeout each) can delay subsequent scheduled work.

---

## ✅ FIXED — Item 10: Password reset tokens readable in queue payloads (P1)

[QueuedPasswordResetJob.php](app/Jobs/QueuedPasswordResetJob.php#L18) implements `Illuminate\Contracts\Queue\ShouldBeEncrypted`:

- Class implements `ShouldBeEncrypted, ShouldQueue`.
- Queue payloads are automatically encrypted by Laravel's queue worker pipeline.
- Verified in `tests/Feature/Jobs/QueuedAuthJobsTest.php`: verifies that the raw token is absent from the serialized database queue `jobs.payload` column, and that worker execution successfully decrypts and delivers the notification.

---

## ❌ OPEN — Item 11: Meeting notifications marked sent before delivery (P2)

[MeetingStarted.php](app/Notifications/Zoom/MeetingStarted.php) and [MeetingEnded.php](app/Notifications/Zoom/MeetingEnded.php) both implement `ShouldQueue`. The parent job sets `*_notification_sent_at` after `Notification::send()` returns, which only queues delivery — not completes it. A dedicated delivery tracking ledger/migration is needed.

---

## ❌ OPEN — Item 12: SMS failures can count as successful batch jobs (P2)

[VonageSmsService.php](app/Services/VonageSmsService.php#L31-L54) returns strings instead of throwing exceptions for missing recipients or non-zero Vonage status. [SmsMessage.php](app/Jobs/SmsMessage.php#L75) ignores the return value, allowing batches to mark `delivered = true` even when delivery failed.

---

## ❌ OPEN — Item 13: Default exception reporting can bypass sanitization (P2 security)

[Handler.php](app/Exceptions/Handler.php#L67-L82) calls `recordZoomException($e)` but also continues to `parent::report($e)` for `ZoomException` instances. Laravel forwards the raw exception object to the default logger, bypassing string-array sanitizers.

---

## ✅ COMPLETED — Item 14 (hardening): Allowlist public Zoom metadata

[ZoomException.php](app/Exceptions/Integrations/Zoom/ZoomException.php#L39-L47) was updated:

- Filters metadata using `array_intersect_key()` against an explicit allowlist: `provider`, `reason`, `retry_after_seconds`.
- Prevents leaking internal exception context to public API responses.
- Verified in `tests/Feature/Exceptions/HandlerReportingTest.php`.

---

## ✅ COMPLETED — Item 15 (hardening): Avoid error-level reporting for expected edit conflicts

[Handler.php](app/Exceptions/Handler.php#L47-L55) was updated:

- Added `EditConflictException::class` to the `$dontReport` array.
- Prevents normal optimistic concurrency collisions from triggering error-level logs and alerts.
- Verified in `tests/Feature/Exceptions/HandlerReportingTest.php`.

---

## Summary Table

| #   | Title                                     | Status                                  |
| --- | ----------------------------------------- | --------------------------------------- |
| 1   | Expired Zoom recovery claim stuck         | ✅ FIXED                                |
| 6   | Same-stage postponed reason discarded     | ✅ FIXED                                |
| 7   | Recovery command limits unbounded         | ⚠️ PARTIALLY FIXED (3 commands pending) |
| 8   | Manual meeting ID unsafe coercion         | ✅ FIXED                                |
| 9   | Provider recovery blocks scheduler        | ❌ OPEN                                 |
| 10  | Password reset token in queue payload     | ✅ FIXED                                |
| 11  | Notifications marked sent before delivery | ❌ OPEN                                 |
| 12  | SMS failures counted as successful        | ❌ OPEN                                 |
| 13  | Exception reporting bypasses sanitization | ❌ OPEN                                 |
| 14  | ZoomException meta not allowlisted        | ✅ COMPLETED                            |
| 15  | EditConflict reported at error level      | ✅ COMPLETED                            |

**6 fixed/completed, 1 partially fixed, 4 open.**
