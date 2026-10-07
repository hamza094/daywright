# Phase 5: Deployment repeatability

Complete these checks in the actual release environment or a staging environment with the same services. Use the deployment platform's existing health checks, process manager, backups, and alerts where they meet the requirement. Create application code only for a missing signal or behavior.

## P5.1: one repeatable release procedure

Update `docs/DEPLOYMENT.md` from the current `composer.json`, `.env.example`, `config/queue.php`, and `app/Console/Kernel.php`. Document the actual PHP/extensions, install from `composer.lock`, required secret names, migration order, cache/build steps, worker restart, scheduler invocation, and a post-deploy smoke check. Check that worker timeouts and queue retry settings allow the Zoom and Paddle recovery leases to work. Run `composer check-platform-reqs --no-dev`, `php artisan route:list`, and `php artisan schedule:list` on the release build. Avoid a new deploy script if the hosting platform already performs these steps reliably.

**Phase 5 webhook migration:**

- Include these Zoom webhook migrations in the documented migration order: `2026_10_05_000001_add_last_zoom_event_timestamp_to_meetings_table`, `2026_10_07_000001_add_sync_reconcile_before_at_to_meetings_table`, and `2026_10_07_000002_change_sync_reconcile_before_at_precision_on_meetings_table`.
- Monitor application clock accuracy because update webhooks compare provider event time with a local reconciliation cutoff to decide whether to fetch Zoom's current state. Clock skew can change whether that lookup occurs; provider event ordering uses only provider timestamps and does not reject events based on the local clock.

## P5.2: detect broken dependencies and stopped work

Prove that the selected monitoring detects database and Redis failures, stopped queue workers, and a stopped scheduler. A queue connection check alone cannot show that a worker is processing jobs; use the platform's worker/scheduler monitoring or a small heartbeat if no signal exists. Give failing checks a nonzero status or an alert. Keep health output free of credentials and private payloads. Test one healthy run and each failure signal in staging. Add a custom health service only for gaps the platform cannot cover.

## P5.3: restore and recover a release

Restore a real backup into an isolated target. Verify representative records, required files, and access to encrypted data without printing secrets. Keep provider calls and queued sends disabled during the exercise so restored operations do not contact customers. Record the backup age, restore time, and data-loss window. Exercise the platform's rollback or forward-recovery procedure with a schema/worker change; avoid reversing a migration that would lose data. Verify the recovered app can read, write, and process controlled queued work.

## P5.4: alerts reach a person

Trigger one controlled queue failure and one health failure in staging. Confirm the configured operator receives both, can identify the affected operation, and has a short procedure for Zoom inbox, Zoom creation, and Paddle manual review. Test the actual destination, not only a mocked reporter. Sanitize alert context and record the delivery result without addresses or secrets.

**Phase 5 webhook-specific alerting:**

- Monitor ignored Zoom webhooks for stale provider timestamps and timestamp-unit problems. `meeting.updated` requires root-level `event_ts`; `meeting.deleted` allows it to be absent and processes an authenticated timestamp-less delete with a critical log. Older events can be ignored by the provider timestamp watermark; a distinct delete wins an equal-timestamp update/delete tie.
- Confirm alerting covers stuck operations in `Updating`, `UpdateFailed`, `Deleting`, or `DeleteFailed` states that may require manual intervention.

## Deferred

P5.5 PHPUnit metadata cleanup can follow launch if warnings are non-blocking and test discovery is unchanged. Do it before release if CI fails or tests disappear.

## Acceptance

Keep a short release record with the build identifier, commands, test results, real-service evidence from Phase 4, restore result, and delivered alert. Mark unexecuted staging or provider checks as open. Release sign-off needs the actual environment checks above; documentation alone is not evidence that restore or alerts work.
