# DayWright Queue and Scheduler Production Hardening Plan

## Summary

Implement this as small phased PRs, in risk order. Keep the existing Laravel `app/Console/Kernel.php` scheduler structure, existing job classes, database queue default, Redis-backed cache/locks, and current service/action conventions. Do not introduce Horizon in this pass.

Final target: scheduled work runs once, queued jobs have safe retry/timeout behavior, external sends are not accidentally duplicated, failed jobs are observable, and tests lock the production-critical behavior.

## Phase 1: Fix Scheduled Message Timing

- Change scheduled-message due checks from date-only comparison to full timestamp comparison.
- Update both due-message selection and future scheduled-message listing so messages scheduled later today are not sent early.
- Keep the existing `schedule:message` command and `DispatchProjectMessageAction` claim flow.

Tests:

- Add a regression test where a message scheduled later today is not dispatched.
- Keep existing repeated-dispatch test passing.
- Add a test for a message scheduled at or before `now()` dispatching normally.

Acceptance:

- `schedule:message` only dispatches messages whose `delivered_at <= now()`.
- Future same-day messages still appear in scheduled-message listings.

## Phase 2: Align Queue Timeouts and Retries

- Set queue `retry_after` above the highest job timeout plus buffer. Use `150` seconds as the default because Zoom webhook jobs currently timeout at `120`.
- Keep database queue as the production default for now.
- Add explicit job policies where missing:
  - Mail/SMS message jobs: `tries = 3`, `timeout = 60`, `backoff = [30, 120]`, `failed()` logs context.
  - Auth email jobs: `tries = 3`, `timeout = 30`, `backoff = [10, 60]`.
  - Keep existing Zoom webhook retry/backoff behavior, only align queue config.

Tests:

- Add a config/contract test asserting queue `retry_after` is greater than known job timeouts.
- Add job tests for mail/SMS failure logging or permanent failure path.

Acceptance:

- No job has `$timeout >= retry_after`.
- Worker command can safely use `--timeout=120` or lower than `retry_after`.

## Phase 3: Make Scheduler Multi-Server Safe

- Add `onOneServer()` and stable `name()` values to scheduled commands that must not run twice:
  - `schedule:message`
  - `tasks:notify`
  - `remove:abandon`
  - `queue:prune-batches`
  - `backup:clean`
  - `backup:run`
  - `telescope:prune`
  - `user:profile-delete`
  - `projects:recalculate-health`
- Add `withoutOverlapping()` to daily cleanup/backup/health commands, with realistic lock windows.
- Keep `runInBackground()` only for fast recurring commands if logs are captured.

Tests:

- Add a scheduler registration test that asserts key scheduled commands have `onOneServer`, `withoutOverlapping`, expected frequency, and stable names.

Acceptance:

- Two production app servers running cron cannot perform duplicate cleanup/backups/health sweeps.
- Scheduler locks rely on Redis cache, so production must have Redis available.

## Phase 4: Fix Abandoned Project Cleanup Path

- Update `remove:abandon` to use the existing abandoned-project deletion action/service instead of calling `forceDelete()` directly.
- Process abandoned projects with `chunkById()` to avoid loading all trashed projects at once.
- Preserve existing behavior for projects with no Zoom meetings, but ensure projects with Zoom meetings dispatch `CancelZoomMeetingsJob`.

Tests:

- Update existing abandoned-project command test to assert old projects are deleted.
- Add a test that abandoned project cleanup dispatches Zoom cancellation when meetings exist.
- Add a test for chunked cleanup with more than one chunk.

Acceptance:

- Scheduled cleanup uses the same safe deletion path as the API force-delete flow.
- Zoom cancellation is not skipped by cron cleanup.

## Phase 5: Harden Task Notification Scheduling

- Change `tasks:notify` from `chunk()` to `chunkById()` because processing mutates `notify_sent`.
- Keep the existing row-lock claim behavior in `SendTaskDueNotificationAction`.
- Keep the existing `notify_sent` idempotency model for now.

Tests:

- Add a multi-chunk test where more than 50 due tasks are all processed.
- Keep repeat-safety test proving a task is notified only once.
- Add one test for concurrent/repeated command behavior if practical with current test tools.

Acceptance:

- Updating `notify_sent` during processing cannot cause later due tasks to be skipped.
- Re-running the command does not duplicate task due notifications.

## Phase 6: External Delivery and Failed Job Handling

- Add minimal idempotency for project message delivery:
  - Mail jobs should avoid resending if the parent message is already delivered or if a per-user delivery record/status already exists.
  - SMS job should avoid resending if the parent SMS message is already delivered.
- If per-recipient delivery tracking is too large for this pass, implement a smaller guard first: check `messages.delivered` before sending and document the remaining duplicate risk.
- Make `DispatchProjectMessageAction` recover stale claim tokens or failed batches so messages do not stay forever with `delivered = false` and non-null `batch_id`.
- Keep the existing `allowFailures()` batch behavior unless adding delivery tracking.

Tests:

- Mail retry does not send again after message is marked delivered.
- SMS retry does not send again after message is marked delivered.
- Failed/stale scheduled message claim can be retried or surfaced clearly.
- Batch failure logs enough message/batch context.

Acceptance:

- A worker retry after external delivery success has a guard against duplicate sends.
- Failed scheduled batches are not silently stuck forever.

## Phase 7: Production Ops Documentation

- Add a short deployment section documenting:
  - Cron: `* * * * * cd /path/to/daywright && php artisan schedule:run >> /dev/null 2>&1`
  - Required workers for `default` and `metrics` queues.
  - Required Redis cache availability for locks/rate limiting.
  - Failed job storage and routine checks using `queue:failed`.
  - Recommended worker flags, with `--timeout` lower than queue `retry_after`.
- Update `.env.example` to make production expectations clear:
  - Keep local default if desired, but add comments showing production should not use `QUEUE_CONNECTION=sync`.
  - Mention database queue as current supported production default.
  - Mention Redis cache requirement.

Tests:

- No runtime tests required.
- Optional docs check only if the repo has one.

Acceptance:

- A deployer can set up cron and workers without guessing.
- The `metrics` queue is explicitly documented so project health jobs do not starve.

## Public Interfaces and Config Changes

- No HTTP API response shape changes.
- No route changes.
- Internal behavior changes only for scheduler/job safety.
- Config/docs changes:
  - Queue `retry_after` default should become `150`.
  - `.env.example` should document production queue/cache requirements.

## Assumptions

- Use database queue for this hardening plan; do not install Horizon yet.
- Redis is required in production for cache, scheduler locks, rate limiting, and `withoutOverlapping` reliability.
- Keep Laravel scheduler registration in `app/Console/Kernel.php`; do not migrate to bootstrap scheduler APIs.
- Keep changes small and phased; no broad queue architecture rewrite.
