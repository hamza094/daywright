# Phase 5 Zoom Sync Correctness Follow-up

## Purpose

Implement the verified Zoom synchronization fixes in small, reviewable phases. Each phase has one concern, its own tests, and a clear stopping point. Do not combine phases in one implementation pass.

The current design remains appropriately engineered for delayed, duplicated, incomplete, missing, and out-of-order Zoom events. The work below removes duplicated logic and closes specific recovery gaps without removing synchronization fields or introducing a new reconciliation system.

Zoom documents `event_ts` as required for `meeting.updated` and optional for `meeting.deleted`; its webhook guide includes a millisecond timestamp example. It retries failed deliveries only three times, PATCH returns no content, and GET returns the current meeting or 404 when it does not exist. [Webhook guide](https://developers.zoom.us/docs/api/webhooks/), [meeting webhook schemas](https://developers.zoom.us/docs/api/meetings/events/), [meeting API](https://developers.zoom.us/docs/api/meetings/).

## Phase 0 — Freeze the baseline

**Goal:** Record the current behavior before changing implementation.

**Work:**

- Run the existing focused Zoom webhook, meeting update, delete, and recovery tests.
- Record the current queue, cache, Zoom HTTP timeout, lock, and recovery lease settings.
- Confirm the working tree contains no unrelated edits to the files being changed.
- Record the current deployed-worker contract from `docs/DEPLOYMENT.md`: database workers use `--timeout=120`, database `retry_after` is 150 seconds, the recovery job has no job-specific timeout, and `stopwaitsecs=3600` is only Supervisor shutdown grace.
- Record the existing scheduler contract: `meetings:recover-pending --limit=25` runs every minute with `onOneServer()` and `withoutOverlapping()`. It requires one shared central cache store across scheduler nodes.

**Tests/evidence:** Existing focused tests pass. The baseline records that the recovery matcher is strict, recovery applies only requested fields, and the current stale-worker test changes ownership during the Zoom lookup rather than after ownership validation.

**Done when:** The baseline is captured in the implementation handoff or review comment, with no code changes in this phase.

## Phase 1 — Share matching and snapshot logic

**Goal:** Remove duplicated webhook/recovery comparison and persistence behavior.

**Likely files:** `ZoomMeetingUpdatedWebhookChanges.php`, `PerformZoomMeetingRecovery.php`, related unit/feature tests.

**Work:**

- Make recovery use the existing normalized matcher for time, number, boolean, and string values.
- Make successful recovery persistence use the canonical Zoom snapshot mapper, including `join_url`.
- Remove the recovery-only strict matcher after the shared behavior is proven.
- Do not change operation states, leases, timestamps, or public API responses.

**Tests:**

- ISO-8601 and database-formatted UTC `start_time` values match.
- Numeric and boolean equivalent values match.
- A matching recovery saves the full Zoom snapshot and a changed `join_url`.
- A mismatching value remains retryable and does not complete the operation.

**Done when:** Webhook and recovery paths use the same semantic matcher and snapshot mapping, and all focused tests pass.

## Phase 2 — Make successful password updates recoverable

**Goal:** Handle the case where Zoom accepts a password PATCH but the webhook is delayed or never arrives.

**Likely files:** `UpdateProjectMeeting.php`, `PerformZoomMeetingRecovery.php`, recovery tests.

**Work:**

- After a successful password PATCH, retain the operation ID and existing encrypted `sync_payload`.
- Under the existing operation-ID check, clear the claim and lease, set `sync_available_at` to one minute later, keep `sync_attempts` unchanged, clear any prior error, and leave `sync_status` as `updating` so the existing scheduler claims it.
- Recovery must GET Zoom, verify the requested password, save the canonical snapshot, including the refreshed `join_url`, and then clear the operation.
- If recovery sees the old password, it may use the existing PATCH retry, but it must leave the operation pending for a later GET. It must never finalize locally from the requested payload alone.
- If GET fails, retain the existing retry/backoff behavior.
- A late successful PATCH response may only schedule verification when the same operation ID is still current. It must not restore `updating`, recreate an operation, or overwrite a newer webhook completion or deletion.

**Tests:**

- Successful password PATCH with no webhook is recovered by the scheduled GET.
- The final local row contains the new password and Zoom's new `join_url`.
- A failed GET leaves the operation retryable.
- A recovery PATCH followed by a later GET is required before completion.
- A webhook that completes or deletes the meeting while a PATCH is in flight remains authoritative after the late PATCH response returns.
- Existing response shape and HTTP status remain unchanged; the temporary status is the existing `updating` value.

**Done when:** Missing password webhooks cannot leave a stale derived join URL, and no new table, endpoint, or generic polling job exists.

## Phase 3 — Handle equal provider timestamps

**Goal:** Make distinct equal-timestamp update events deterministic enough to reconcile current Zoom state.

**Likely files:** `ZoomWebhookSupport.php`, `HandleMeetingUpdatedWebhook.php`, `ZoomMeetingUpdatedWebhookProcessor.php`, timestamp tests.

**Work:**

- Keep duplicate delivery deduplication in the webhook inbox.
- Continue rejecting strictly older provider events.
- For a distinct update whose provider timestamp equals the stored watermark, GET Zoom and apply the current canonical snapshot.
- Capture the current provider watermark in `MeetingUpdateWebhookContext` before any Zoom GET. Under the row lock, validate that the operation ID, reconciliation cutoff, and provider watermark still match the captured context.
- For `meeting.updated`, treat a timestamp lower than the locked watermark as stale. Treat an equal timestamp as reconciliation-required after inbox deduplication. If an event becomes equal while it waited for the row lock and its context has no snapshot, throw a retryable exception so durable inbox processing rebuilds the context and reads Zoom.
- Preserve the existing policy that an equal-timestamp delete wins over an update.
- Keep provider `event_ts` as the ordering clock; never substitute application processing time.

**Tests:**

- Two distinct update events with the same millisecond timestamp reconcile to current Zoom state.
- Build update A's context below its timestamp, let update B with the same timestamp complete first, then resume A. Verify A retries and cannot apply its webhook payload until a rebuilt context includes a Zoom snapshot.
- Equal-timestamp update followed by delete leaves the meeting deleted.
- Equal-timestamp delete followed by delayed update cannot resurrect the meeting.
- Existing duplicate, stale, and out-of-order tests continue to pass.

**Done when:** Equal timestamps no longer cause a distinct update to be silently discarded, while delete precedence and duplicate handling remain unchanged.

## Phase 4 — Remove the redundant reconciliation check

**Goal:** Simplify webhook processing after the shared context invariant is proven.

**Likely files:** `ZoomMeetingUpdatedWebhookProcessor.php`, its existing feature tests.

**Work:**

- Remove only the second reconciliation-needed predicate after Phase 3's provider-watermark context validation and concurrency test pass.
- Keep the operation ID, reconciliation-cutoff, and provider-watermark consistency checks.
- Keep the early ownership check because it avoids unnecessary Zoom GET calls.
- Keep `sync_reconcile_before_at` and `last_zoom_event_timestamp`; they represent different time domains.

**Tests:**

- Context created before a concurrent local change is rejected.
- Context created before another event advances the provider watermark is retried or rejected before it writes local webhook payload.
- Reconciliation-required events still use the Zoom snapshot.
- Events that do not require reconciliation still apply normalized webhook changes.
- No extra Zoom GET is introduced for ordinary current events.

**Done when:** One redundant branch is removed and all existing ordering, context, and reconciliation tests pass.

## Phase 5 — Bound stale recovery workers operationally

**Goal:** Reduce the window in which an old recovery worker can reach Zoom after its local ownership expires.

**Likely files:** `RecoverZoomMeetingOperationJob.php`, queue configuration, deployment/supervisor documentation, concurrency tests.

**Work:**

- Set `public int $timeout = 90` on `RecoverZoomMeetingOperationJob`; Laravel gives the job value precedence over the current worker `--timeout=120` setting.
- Require PCNTL in every production worker PHP runtime and verify Laravel timeout enforcement with the real queue worker. Keep Zoom's 30-second HTTP request timeout because Laravel warns that blocking I/O may not honor a job timeout promptly.
- Keep Supervisor's role limited to restarting the worker after Laravel exits on timeout. Do not use `stopwaitsecs` as a per-job deadline; the current value of 3600 seconds is shutdown grace, not job-time enforcement.
- Keep the per-meeting cache lock at 120 seconds and the recovery claim lease at five minutes.
- Set every deployed queue retry/visibility timeout above 120 seconds. The current Beanstalkd value is 90 seconds and must be raised if Beanstalkd is used.
- Require shared Redis for production cache locks; the repository file-cache default is not sufficient across workers or hosts.
- Set an explicit five-minute `withoutOverlapping(5)` expiry on `webhooks:recover-pending` and `meetings:recover-pending`. Both commands run every minute and use durable database claims, so a scheduler crash must not suppress recovery for Laravel's default 24-hour lock lifetime.
- Update deployment documentation to list PCNTL as required for queue workers, retain the worker `--timeout=120` command, explain that the recovery job's 90-second property takes precedence, and document `schedule:clear-cache` for abandoned scheduler locks.
- Document that this is a bounded operational guarantee, not absolute provider fencing. Zoom does not receive or validate the claim token.

**Tests/evidence:**

- Test concurrent claims with MySQL and shared Redis.
- Verify the recovery job's 90-second Laravel timeout with PCNTL enabled, then separately verify Supervisor restarts the exited worker.
- Pause a worker immediately after ownership validation, allow lock and lease expiry, and let a replacement complete. Record that the old worker exits before it resumes to the Zoom mutation.
- Terminate each affected scheduled recovery command while it owns its overlap lock and verify that recovery resumes after at most five minutes, or that `schedule:clear-cache` clears an abandoned lock during incident recovery.
- Record effective job timeout, worker CLI timeout, Supervisor restart behavior, lock TTL, queue retry/visibility timeout, Zoom request timeout, and operation lease.

**Done when:** Production configuration satisfies the timing inequalities and the evidence explicitly states the remaining provider-fencing limitation.

## Phase 6 — Final regression and release evidence

**Goal:** Prove the complete behavior without broadening scope.

**Required regression matrix:**

- Duplicate webhook delivery.
- Strictly stale and out-of-order updates.
- Delayed update after delete.
- Distinct equal-timestamp updates.
- Equal-timestamp delete precedence.
- Pending update with matching and mismatching fields.
- Missing password webhook and refreshed `join_url`.
- Recovery GET timeout and retry.
- Recovery PATCH timeout and retry.
- Worker crash, lease expiry, and concurrent replacement.
- Recovery scheduler lock expiry and abandoned-lock clearing.
- Pending update reconciled with Zoom 404 becomes locally `Deleted`.
- Authenticated timestamp-less delete remains local-only and critically logged.

**Release boundaries:**

- No new public endpoint, response field, HTTP status, table, or generic reconciliation job.
- Do not remove `sync_claim_token`, `sync_lease_expires_at`, `sync_operation_id`, `sync_operation_type`, `sync_reconcile_before_at`, `last_zoom_event_timestamp`, `synced_at`, or the existing state-machine statuses in this follow-up.
- Local tests prove application behavior. MySQL, shared Redis, PCNTL timeout enforcement, supervisor restart, scheduler lock expiry, queue timing, and Zoom provider behavior remain production or staging evidence gates.

**Overall done when:** Phases 1–5 are implemented and individually green, Phase 6 passes the regression matrix, and production timing/configuration evidence is attached to the release record.
