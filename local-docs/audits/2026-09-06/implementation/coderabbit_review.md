I reviewed the remaining CodeRabbit suggestions against the current code and focused tests. Resolved suggestions have been removed from this active review; the original CodeRabbit numbering is retained for traceability.

This document tracks 9 confirmed defects that remain open or partially fixed, plus 2 valid hardening recommendations. It does not itself make code changes.

The original focused review baseline was 93 tests and 290 assertions. Later changes added more focused coverage; these tests still do not replace real multi-process and provider verification.

## Confirmed defects

### 1. Expired Zoom recovery claim becomes permanently stuck — Valid, P1 ⚠️ PARTIALLY FIXED

`RecoverAmbiguousZoomMeeting::claim()` changes an expired `Creating` meeting to `CreateUnknown`, but leaves `sync_available_at` as `NULL`.

After the worker dies:

- `Creating` scope no longer matches.
- `CreateUnknownDueAt()` excludes `NULL`.
- The claim token prevents manual resolution.

**Status:** PARTIALLY FIXED

- New `RecoverPendingZoomMeetingOperations` sets `sync_available_at` when it claims work.
- Queue-based recovery (`RecoverZoomMeetingOperationJob`) protects the new update/delete path.
- The legacy `RecoverAmbiguousZoomMeeting` path is still scheduled and retains the old claim behavior, so item 1 is not fully closed.

### 6. Same-stage postponed reason changes are discarded — Valid, P2 ❌ OPEN

[ProjectService.php](C:/Users/Hamza/daywright/app/Services/Project/ProjectService.php:171) returns immediately when the stage is unchanged, so a new `postponed_reason` is ignored and the version is not incremented.

Fix:

- Calculate the desired postponed reason.
- Return as a no-op only when both stage and reason are unchanged.
- Update only the reason and increment `version` when the stage is unchanged.
- Do not run stage-transition side effects for a reason-only edit unless the existing product contract requires notification.

Add a service/API test asserting the new reason and incremented version.

### 7. Recovery command limits accept unbounded input — Valid, P2 ⚠️ PARTIALLY FIXED

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

### 8. Manual meeting IDs are coerced unsafely — Valid, P2 ❌ OPEN

`(int) '123abc'` becomes `123`, and `(int) '123.9'` also becomes `123`.

Affected file: [ResolveAmbiguousZoomMeeting.php](C:/Users/Hamza/daywright/app/Console/Commands/ResolveAmbiguousZoomMeeting.php:31)

Fix:

- Validate the original argument first.
- Accept only a positive integer within the PHP/database range.
- Reject prefixes, decimals, signs, zero, and overflow.
- Perform no lookup when validation fails.

### 9. Provider recovery blocks the scheduler process — Valid, P2 operational issue ❌ OPEN

The meeting recovery command can perform 25 sequential Zoom calls with 30-second timeouts. It runs before subscription recovery, so a provider outage can delay all later scheduled work.

Fix:

- Use `runInBackground()` for provider-backed scheduled commands, or dispatch bounded recovery jobs.
- Preserve `onOneServer()`, `withoutOverlapping()`, and row-level claim checks.
- Add a schedule test proving background execution.
- Verify behavior on the actual production scheduler platform.

### 10. Password reset tokens are readable in queue payloads — Valid, P1 ❌ OPEN

[QueuedPasswordResetJob.php](C:/Users/Hamza/daywright/app/Jobs/QueuedPasswordResetJob.php:17) stores the raw reset token and does not implement `ShouldBeEncrypted`.

A queue or failed-job database reader could retrieve a still-valid token.

Fix:

- Implement `Illuminate\Contracts\Queue\ShouldBeEncrypted`.
- Verify the raw token is absent from the serialized database queue payload.
- Verify normal worker decryption and delivery.
- Review and drain existing unencrypted queued/failed reset jobs before deployment.
- Do not change reset token generation or the public reset API.

### 11. Meeting notifications are marked sent before delivery — Valid, P2 ❌ OPEN

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

### 12. SMS failures can count as successful batch jobs — Valid, P2 ❌ OPEN

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

### 13. Default exception reporting can bypass sanitization — Valid, P2 security risk ❌ OPEN

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

14. Allowlist public Zoom metadata. `ZoomException::meta()` currently spreads all context into API responses. Expose only explicitly approved fields such as provider, reason, and validated retry seconds. **Status: OPEN.**

15. Avoid error-level reporting for expected edit conflicts. `EditConflictException` represents normal optimistic-concurrency behavior and can be excluded from default error reporting while retaining a metric or lower-severity event. **Status: OPEN.**

## SWE 1.6 implementation order

Use one ticket per run:

1. Zoom expired-claim recovery.
2. Same-stage postponed-reason update.
3. Recovery command input validation.
4. Manual meeting-ID validation.
5. Background provider recovery scheduling.
6. Encrypted password-reset queue job.
7. SMS typed outcomes and delivery failure recording.
8. Meeting notification delivery ledger.
9. Controlled provider exception reporting.
10. Optional hardening ticket for metadata and edit-conflict reporting.

For every ticket, require:

- Exact starting files.
- Focused regression tests first or alongside the change.
- No changes to unrelated frontend/OpenAPI files.
- `git diff --check`.
- Focused PHPUnit tests.
- `composer stan` and `composer pint:test` where applicable.
- A final note distinguishing local tests from real MySQL, Redis, worker, scheduler, and provider-sandbox evidence.

The existing modified files in the worktree were preserved and not included in this review’s changes.

---

## Phase 4 & 5 Implementation Status

### Current status summary

**Partially fixed:** Items 1 and 7. The new meeting recovery command/path has the lease or limit protection, but the legacy create-recovery and other scheduled recovery commands still need to be migrated, validated, or retired.

**Open:** Items 6, 8, 9, 10, 11, 12, 13, 14, and 16.

**Phase 5 Zoom follow-up:** The implementation and deployment findings from the latest review are tracked in [phase-5-zoom-integration-remediation.md](C:/Users/Hamza/daywright/local-docs/audits/2026-09-06/implementation/phase-5-zoom-integration-remediation.md). They remain open until the listed fixes and regression coverage are complete.

The open and partial items require separate implementation tickets following the SWE 1.6 implementation order. Local tests do not replace production verification with MySQL, Redis, multiple workers, the scheduler, and provider sandboxes.
