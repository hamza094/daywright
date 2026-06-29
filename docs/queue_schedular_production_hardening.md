# DayWright Queue, Scheduler, and Notification Production Hardening Plan

## Summary

Implement this as small phased PRs, in risk order. Keep the existing Laravel `app/Console/Kernel.php` scheduler structure, database queue production default, Redis-backed cache/locks, and current service/action conventions. Do not introduce Horizon in this pass.

This revision was cross-checked against `.github/skills/laravel-queue.md` and now explicitly covers queued notifications, stale serialized models, queue priority separation, after-commit behavior, duplicate prevention, failed job logging, and task scheduler failure visibility.

Final target: scheduled work runs once, queued jobs have safe retry/timeout behavior, external sends are not accidentally duplicated, failed critical jobs are observable, notification failures are not hidden, and tests lock the production-critical behavior.

## Phase 1: Fix Scheduled Message Timing ✅ COMPLETED

- Change scheduled-message due checks from date-only comparison to full timestamp comparison.
- Update both due-message selection and future scheduled-message listing so messages scheduled later today are not sent early.
- Keep the existing `schedule:message` command and `DispatchProjectMessageAction` claim flow.
- Use app/database timezone consistently in the query and tests so production cron timing is predictable.

Tests:

- Add a regression test where a message scheduled later today is not dispatched.
- Keep existing repeated-dispatch test passing.
- Add a test for a message scheduled at or before `now()` dispatching normally.
- Add a timezone-boundary case if the app stores `delivered_at` in UTC but schedules in the user's/app timezone.

Acceptance:

- `schedule:message` only dispatches messages whose `delivered_at <= now()`.
- Future same-day messages still appear in scheduled-message listings.
- The scheduler `when()` condition uses the same due scope as the command.

## Phase 2: Align Queue Timeouts, Retries, and After-Commit Defaults

- Set queue `retry_after` above the highest job timeout plus buffer. Use `150` seconds as the default because Zoom webhook jobs currently use overlap locks expiring at `120` seconds and the worker should never retry a still-running job.
- Keep database queue as the production default for now, but align the `redis` connection too so a future Redis queue switch does not regress.
- Set `after_commit = true` on all production queue connections that may be used (`database` already has it; align `redis`).
- Add explicit job policies where missing:
  - Mail/SMS project message jobs: `$tries = 3`, `$timeout = 60`, `$failOnTimeout = true`, `backoff = [30, 120]`, `failed()` logs message/project/user context.
  - Auth email jobs: `$tries = 3`, `$timeout = 30`, `$failOnTimeout = true`, `backoff = [10, 60]`, `failed()` logs user/auth context without tokens.
  - Zoom meeting notification wrapper jobs: decide intentionally between `tries = 1` to avoid duplicate external sends or `tries > 1` only after idempotency/per-recipient tracking exists.
  - `CancelZoomMeetingsJob`: add `timeout`, `failOnTimeout`, and keep/review its 429 release/backoff behavior.
  - Keep existing Zoom webhook retry/backoff behavior, only align queue config and worker flags.
- Avoid relying only on global worker defaults for critical external side effects.

Tests:

- Add a config/contract test asserting queue `retry_after` is greater than known job timeouts.
- Add tests that all critical jobs define expected `tries`, `timeout`, `backoff`, and `failOnTimeout` policy.
- Add job tests for mail/SMS/auth failure logging or permanent failure paths.

Acceptance:

- No job has `$timeout >= retry_after`.
- Worker command can safely use `--timeout=120` or lower than queue `retry_after`.
- Jobs dispatched inside transactions are queued only after commit.

## Phase 3: Define Queue Priority and Worker Topology ✅ COMPLETED

- Add a small production queue map before changing dispatches:
  - `critical`: password reset, verification, user-facing auth/security email, and any future payment/security jobs.
  - `default`: normal project messages, task notifications, meeting notification wrappers, and webhook work unless a stronger reason exists.
  - `metrics`: project health recalculation jobs.
- Move auth email jobs to `critical` using a `$queue` property or `onQueue('critical')`.
- Consider whether Zoom webhook jobs should remain on `default` or move to a dedicated `webhooks` queue. If moved, document and provision a worker in the same PR.
- Do not move all notification classes blindly; queued notification behavior must match idempotency and failure semantics.

Tests:

- Add dispatch tests proving auth jobs land on `critical`.
- Keep existing `metrics` queue dispatch tests for project health.
- Add a worker documentation check only if the repo has a docs lint/check pattern.

Acceptance:

- Production workers can process priority order with `--queue=critical,default`.
- Metrics jobs have a separate worker so health sweeps do not starve user-facing work.
- The deployment docs name every queue that code can dispatch to.

## Phase 4: Make Scheduler Multi-Server Safe and Observable ✅ COMPLETED

- Add `onOneServer()` and stable `name()` values to scheduled commands that must not run twice:
  - `schedule:message`
  - `tasks:notify`
  - `remove:abandon`
  - `queue:prune-batches`
  - `queue:prune-failed`
  - `backup:clean`
  - `backup:run`
  - `telescope:prune`
  - `user:profile-delete`
  - `projects:recalculate-health`
- Add `withoutOverlapping()` to cleanup/backup/health commands with realistic lock windows.
- Keep `runInBackground()` only where output is captured. Add scheduler output logging for recurring commands, for example `appendOutputTo(storage_path('logs/scheduler.log'))`, or remove background execution if logs are otherwise lost.
- Add `queue:prune-failed --hours=720` or an agreed retention window so `failed_jobs` does not grow forever.
- Document `schedule:interrupt` as part of zero-downtime deploys when schedule definitions change.

Tests:

- Add a scheduler registration test that asserts key scheduled commands have `onOneServer`, `withoutOverlapping`, expected frequency, stable names, and scheduler output handling.

Acceptance:

- Two production app servers running cron cannot perform duplicate cleanup/backups/health sweeps.
- Scheduler locks rely on Redis cache, so production must have Redis available and configured as the cache store.
- Scheduler failures and background command output are reviewable after production incidents.

## Phase 5: Fix Abandoned Project Cleanup Path ✅ COMPLETED

- Update `remove:abandon` to use `ForceDeleteAbandonedProjectAction` instead of calling `forceDelete()` directly.
- Process abandoned projects with `chunkById()` to avoid loading all trashed projects at once.
- Preserve existing behavior for projects with no Zoom meetings, but ensure projects with Zoom meetings dispatch `CancelZoomMeetingsJob`.
- Keep `CancelZoomMeetingsJob::dispatch($meetings)->afterCommit()` through the shared action.

Tests:

- Update existing abandoned-project command test to assert old projects are deleted.
- Add a test that abandoned project cleanup dispatches Zoom cancellation when meetings exist.
- Add a test for chunked cleanup with more than one chunk.
- Add a test that non-trashed projects are ignored by the shared action.

Acceptance:

- Scheduled cleanup uses the same safe deletion path as the API force-delete flow.
- Zoom cancellation is not skipped by cron cleanup.
- Large cleanup runs do not load every abandoned project into memory.

## Phase 6: Harden Task Notification Scheduling ✅ COMPLETED

- Change `tasks:notify` from `chunk()` to `chunkById()` because processing mutates `notify_sent`.
- Keep the existing row-lock claim behavior in `SendTaskDueNotificationAction`.
- Decide and document the exact meaning of `notify_sent`:
  - If it means "notification queued", keep setting it before queued notification dispatch, but add failure visibility for queued notification jobs.
  - If it means "notification delivered", introduce a separate queued/delivered tracking field or notification delivery table before changing behavior.
- Add failure logging for task notification enqueue/send failures with task ID, project ID, assignee IDs, and notification type.
- Consider adding `viaQueues()` or queue assignment for `TaskDue` so its mail/database/broadcast work does not unexpectedly compete with critical auth jobs.

Tests:

- Add a multi-chunk test where more than 50 due tasks are all processed.
- Keep repeat-safety test proving a task is notified only once.
- Add one test for concurrent/repeated command behavior if practical with current test tools.
- Add a failure-path test proving task notification exceptions are logged with task/project context.

Acceptance:

- Updating `notify_sent` during processing cannot cause later due tasks to be skipped.
- Re-running the command does not duplicate task due notifications.
- If queued notification delivery fails later, the failure is visible in logs/failed jobs and not mistaken for scheduler success.

## Phase 7: Harden Project Message Delivery and Batch Recovery ✅ COMPLETED

- Add minimal idempotency for project message delivery:
  - Mail jobs should avoid resending if the parent message is already delivered or if a per-user delivery record/status already exists.
  - SMS job should avoid resending if the parent SMS message is already delivered.
- If per-recipient delivery tracking is too large for this pass, implement a smaller guard first: check `messages.delivered` before sending and document the remaining duplicate risk for multi-recipient mail.
- Make `MailMessage` and `SmsMessage` payloads smaller and less stale:
  - Prefer passing IDs and loading fresh `Project`, `Message`, and `User` records in `handle()`.
  - If keeping model parameters, use Laravel 12's `#[WithoutRelations]` where appropriate and ensure missing models are handled cleanly.
- Add `Batchable` cancellation checks at the start of batched jobs.
- Make `DispatchProjectMessageAction` recover stale claim tokens or failed batches so messages do not stay forever with `delivered = false` and non-null `batch_id`.
- Review whether `allowFailures()` should remain. If it stays, document that a batch may mark a message delivered even when one recipient failed unless per-recipient delivery tracking is added.

Tests:

- Mail retry does not send again after message is marked delivered.
- SMS retry does not send again after message is marked delivered.
- Batched jobs return early when their batch is cancelled.
- Failed/stale scheduled message claim can be retried or surfaced clearly.
- Batch failure logs enough message/batch/project context.

Acceptance:

- A worker retry after external delivery success has a guard against duplicate sends.
- Failed scheduled batches are not silently stuck forever.
- Job payloads do not serialize unnecessary relationships or stale model state.

## Phase 8: Harden Meeting and General Notification Delivery

- Keep one intentional queuing layer for Zoom meeting notifications:
  - Current state: `SendMeetingStartedNotification` and `SendMeetingEndedNotification` are queued unique jobs, while `MeetingStarted` and `MeetingEnded` notification classes are not queued.
  - Do not simply add `ShouldQueue` to the Zoom notification classes unless the sent-marker flow is redesigned, because the wrapper job could mark a meeting as notified before queued per-channel notification jobs actually succeed.
- Re-check sent flags immediately before sending inside `SendMeetingStartedNotification` and `SendMeetingEndedNotification`.
- Add a durable sent/claim strategy for meeting notifications:
  - Either mark a notification as claimed before send and reset claim on failure, or add per-recipient notification delivery records.
  - Avoid duplicate meeting emails when a worker dies after sending but before updating `*_notification_sent_at`.
- Add explicit queue policy to meeting notification wrapper jobs: timeout, backoff or no-retry decision, failure logging, and queue name.
- Review all app notification classes that implement `ShouldQueue` and ensure production-critical ones have appropriate queue assignment and after-commit behavior.
- Keep existing `afterCommit()` calls on notification constructors and add them where notifications are dispatched from transactional flows.

Tests:

- Started/ended meeting notification jobs do not send when sent flags are already set.
- Failed meeting notification jobs log meeting ID and notification type.
- Duplicate webhook dispatch does not duplicate meeting notification jobs for the same meeting inside the uniqueness window.
- Queued notification classes used by task/project flows have coverage for channel payloads and queue assignment where added.

Acceptance:

- Meeting started/ended notifications have an explicit duplicate/failure strategy.
- Critical notification failures are visible through logs and failed jobs.
- Adding `ShouldQueue` to a notification class cannot accidentally hide delivery failure behind an already-updated sent flag.

## Phase 9: Critical Queue Failure Logging and Operations

- Add a dedicated `queue_critical` logging channel or equivalent structured logging path for permanent failures of critical jobs.
- Register a global `Queue::failing` listener that logs:
  - job class/display name
  - queue and connection
  - job UUID/attempts when available
  - exception class/message
  - safe business context from job `tags()` or explicit metadata
- Keep per-job `failed()` methods for jobs where business context matters:
  - Zoom webhook jobs already log to `zoom_webhook_failed`; keep and test this.
  - `CancelZoomMeetingsJob` logs to `zoom`; ensure permanent failures are also captured by global queue failure logging.
  - Mail/SMS/auth/meeting notification jobs should log safe identifiers.
- Add `tags()` to critical jobs where useful for Telescope/Bugsnag/log correlation.
- Do not log secrets, reset tokens, OAuth tokens, Zoom webhook secrets, or full notification payloads.
- Add an operational checklist:
  - inspect `php artisan queue:failed`
  - retry targeted failures with `queue:retry`
  - forget known non-retryable failures with `queue:forget`
  - verify logs in `storage/logs`, Bugsnag, and Log Viewer
  - alert on growth of `failed_jobs`

Tests:

- Add tests for `failed()` methods on critical jobs.
- Add a test or integration assertion that the global failing listener writes safe metadata.
- Add a test that sensitive auth tokens are not present in auth job failure logs.

Acceptance:

- Permanent queue failures leave enough evidence to diagnose the failed business operation.
- Critical queue failures are visible outside the `failed_jobs` table.
- Logs are safe to ship to third-party error monitoring.

## Phase 10: Clean Up Incomplete or Unused Queue Surfaces ✅ COMPLETED

- Decide whether `app/Jobs/Webhooks/Mailgun/ProcessEmailStatusWebhook.php` is intentionally a placeholder.
- If unused, remove it or exclude it from production dispatch paths.
- If used, implement its payload, queue policy, idempotency key, failure logging, and tests before enabling any Mailgun webhook route.
- Review listeners and mailables for synchronous external work:
  - `SendPasswordUpdateEmail` sends a queued mailable, which is acceptable, but ensure worker priority handles it.
  - Controller/action notification dispatches should remain acceptable because notification classes mostly implement `ShouldQueue`; add queue assignment only where production priority requires it.

Tests:

- Add tests only if the Mailgun job becomes real production behavior.

Acceptance:

- No placeholder queue job can be mistaken for production-ready processing.
- Synchronous mail/notification work is either intentionally tiny or backed by queued classes.

## Phase 11: Production Ops Documentation

- Add a short deployment section documenting:
  - Cron: `* * * * * cd /path/to/daywright && php artisan schedule:run >> /dev/null 2>&1`
  - Required workers for `critical`, `default`, and `metrics` queues.
  - Recommended worker flags, with `--timeout` lower than queue `retry_after`.
  - Required Redis cache availability for scheduler locks, job locks, rate limiting, and `withoutOverlapping`.
  - Failed job storage and routine checks using `queue:failed`.
  - Failed job pruning schedule and retention.
  - Deployment use of `php artisan queue:restart` and `php artisan schedule:interrupt`.
- Suggested worker commands:
  - `php artisan queue:work database --queue=critical,default --sleep=3 --tries=3 --timeout=120 --max-time=3600`
  - `php artisan queue:work database --queue=metrics --sleep=3 --tries=2 --timeout=120 --max-time=3600`
- Update `.env.example` to make production expectations clear:
  - Keep local default if desired, but add comments showing production should not use `QUEUE_CONNECTION=sync`.
  - Mention database queue as current supported production default.
  - Mention Redis cache requirement.
  - Mention `LOG_CHANNEL=stack` should include production alerting such as Bugsnag/Sentry/Slack where configured.

Tests:

- No runtime tests required.
- Optional docs check only if the repo has one.

Acceptance:

- A deployer can set up cron and workers without guessing.
- Every queue used by the app has a worker plan.
- The `metrics` queue is explicitly documented so project health jobs do not starve.

## Public Interfaces and Config Changes

- No HTTP API response shape changes.
- No route changes.
- Internal behavior changes only for scheduler/job/notification safety.
- Config/docs changes:
  - Queue `retry_after` default should become `150` for database and Redis queue connections.
  - Redis queue `after_commit` should be set to `true` if Redis is ever used for production jobs.
  - `.env.example` should document production queue/cache/logging requirements.
  - Add/confirm log channels for critical queue failures.

## Assumptions

- Use database queue for this hardening plan; do not install Horizon yet.
- Redis is required in production for cache, scheduler locks, job locks, rate limiting, and `withoutOverlapping` reliability.
- Keep Laravel scheduler registration in `app/Console/Kernel.php`; do not migrate to bootstrap scheduler APIs.
- Keep changes small and phased; no broad queue architecture rewrite.
- Treat `notify_sent` and meeting `*_notification_sent_at` fields carefully because they currently mix "queued/sent" semantics depending on the flow.
- Critical production failures must be diagnosable from `failed_jobs`, structured logs, and the configured error monitor.
