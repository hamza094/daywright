# Phase 4: Production readiness

This phase proves the release code works with its intended database, Redis, queue worker, scheduler, and external providers. Complete one ticket at a time, in order. Keep the fast SQLite suite and reuse the existing MySQL 8.4 CI job in `.github/workflows/tests.yml`. Use isolated non-customer data and a separate staging environment for worker and provider exercises.

## How to hand off one ticket

Give the implementer this document and **one** ticket below. Add this instruction:

> Inspect the current code and repository guidelines first. Implement only this ticket. Preserve unrelated changes and existing SQLite coverage. Reuse existing services, recovery commands, CI jobs, and deployment tools. Run focused checks for any code change. Record the files changed, exact commands and results, environment, final database state, provider-stub call counts where relevant, and open checks. Do not mark an unrun staging or provider check complete.

Use the ticket's **Done when** as its stopping point. If a ticket reveals a defect, fix that defect and add a regression test for the missing behavior; avoid building a broad new test matrix. Never use synchronous exception tests, SQLite row locks, mocked provider responses, or documentation alone as proof of the corresponding real-process or provider check.

## Ticket 1 — P4.1a: Confirm real database and Redis configuration

**Start with:** `.github/workflows/tests.yml`, `phpunit.xml`, `config/database.php`, `config/cache.php`, `config/queue.php`, and `.env.example`.

Check the release candidate against the intended MySQL engine and shared Redis. Make the resolved database, cache, and queue configuration observable in both a request process and a separate worker process without printing credentials. Retain the SQLite CI job and its foreign-key coverage. Reuse the MySQL CI job for repeatable database and Redis checks; add a separate harness only if a required check cannot run there. Use committed fixtures visible to both processes. Record PHP, MySQL, Redis, and queue driver versions and the commands used.

**Done when:** the SQLite and MySQL jobs still pass, the database and Redis checks can be rerun, and recorded configuration shows neither in-memory SQLite nor array cache nor a `sync` queue for the real-service exercise.

## Ticket 2 — P4.1b: Run a real worker and scheduler

**Start with:** `app/Console/Kernel.php`, `config/queue.php`, and the existing Zoom/Paddle recovery commands.

In isolated staging, run a worker in a separate process and run the scheduler every minute. Dispatch one controlled job and confirm that the worker processes it. Confirm `webhooks:recover-pending`, `meetings:recover-ambiguous`, and `subscriptions:recover-operations` are scheduled and can execute. Verify that all scheduler nodes share Redis so `onOneServer()` and `withoutOverlapping()` coordinate correctly. Use the deployment platform's existing worker and scheduler facilities where suitable.

**Done when:** the worker process, scheduled invocations, processed job, resolved configuration, and commands are recorded. A successful `schedule:list` by itself is insufficient.

## Ticket 3 — P4.2a: Race task and project edits

**Start with:** `tests/Feature/Api/V1/Tasks/TaskVersioningTest.php`, `tests/Feature/Api/V1/Projects/ProjectVersioningTest.php`, and the task/project update services.

Use two separate processes against the intended MySQL engine. Place a barrier before the competing writes so they overlap. For both a task and a project, submit two updates based on the same version. Confirm that one permitted update succeeds, the stale update receives the published `409 edit_conflict` envelope, and the final row has the correct business fields and version. Test a stage transition if it uses a distinct write path.

**Done when:** the process-level results, response bodies, and final database rows are recorded. Keep the existing request tests passing; add a regression only for an uncovered failure.

## Ticket 4 — P4.2b: Race duplicate and conflicting operations

**Start with:** the existing idempotency tests, `SubscriptionOperation`, `SubscriptionService`, `WebhookInbox`, and their recovery actions.

Use controlled two-process overlap on MySQL and shared Redis. Exercise: one request repeated with the same idempotency key; two incompatible subscription changes for one user; and two recovery workers claiming the same Zoom inbox or subscription operation. Check the documented replay or in-progress response, one business effect, at most one Paddle mutation attempt per operation, and only the claim owner completing it. Use a counting provider stub and inspect final database state. Include an OAuth token-refresh race if that path is used in the first release and has not already been verified.

**Done when:** responses, business effects, final state, and provider-stub call counts are recorded for each race. Keep the existing `SubscriptionOperation` idempotency and claim safeguards.

## Ticket 5 — P4.3a: Interrupt and recover Zoom work

**Start with:** `docs/WEBHOOK_INBOX.md`, `app/Console/Commands/RecoverPendingWebhooks.php`, `app/Console/Commands/RecoverAmbiguousZoomMeetings.php`, and the existing crash-recovery tests.

With real worker and scheduler processes, demonstrate these failure windows:

1. A Zoom webhook is saved in the database while initial queue dispatch is unavailable; later recovery processes it.
2. A worker stops after claiming a Zoom inbox row; another worker resumes after the lease expires.
3. Zoom accepts a meeting create but its response is lost; the local operation stays unknown until provider lookup or manual review, and no second create is sent.

In a real Zoom sandbox, confirm that the creation request and corresponding webhook carry the same operation ID before relying on webhook correlation. If Zoom cannot prove an ambiguous result, keep it visible for manual review. Record the worker exit, recovery command, final database state, and outcome.

**Additional Phase 5 webhook checks:**

- Verify Zoom's `event_ts` unit using real Zoom sandbox payloads (milliseconds vs seconds). Zoom documents `event_ts` as required but does not specify its unit in the meeting webhook schema. Incorrect unit assumption will break timestamp-based staleness detection.
- Test webhook row-lock behavior against MySQL (SQLite tests do not prove real database row-lock behavior).

**Done when:** all three real-process outcomes and the sandbox operation-ID result have evidence, and the webhook timestamp unit and MySQL row-lock behavior are verified. A synchronous exception test alone does not complete this ticket.

## Ticket 6 — P4.3b: Recover Paddle work and verify Paddle Classic

**Start with:** `app/Actions/Subscription/RecoverSubscriptionOperation.php`, `app/DataTransferObjects/Paddle/PaddleSubscriptionSnapshot.php`, `app/Console/Commands/RecoverSubscriptionOperations.php`, `tests/Feature/Api/Webhooks/Paddle/PaddleWebhookTest.php`, and the existing subscription service tests.

First, interrupt a swap or cancellation after Paddle accepts it but before the response is recorded. Show that scheduled recovery reads Paddle, reconciles the local record only after an exact match, and never blindly repeats the mutation. Keep uncertain results visible as `unknown` or `manual_review`. Preserve claim tokens, leases, row locks, transactions, bounded backoff, and remote verification.

Then use **Paddle Classic sandbox** to check subscription creation, successful and failed payment webhooks, swap, cancellation, duplicate-key behavior, and an uncertain-response recovery where practical. Capture a sanitized real Classic cancellation response and check `PaddleSubscriptionSnapshot::provesCancelSucceeded()` against its actual fields. Verify `/paddle/webhook` is reachable over HTTPS, `PADDLE_PUBLIC_KEY` is set, `CASHIER_WEBHOOK` matches the configured URL, required events are enabled, and Cashier's route and signature handling work. Observe that the local subscription, receipt, and safe audit entry match provider events, including duplicate or out-of-order delivery where the sandbox permits it. Check `php artisan route:list --name=cashier.webhook`.

Cashier owns standard Paddle webhooks; its native route has no durable inbox. Record the known risk that an update or cancellation may arrive before the local subscription and that old and new updates are not ordered. Include this limitation and the manual reconciliation procedure in the Phase 4 sign-off record and deployment documentation. Check unfinished historical Paddle inbox rows without deleting them. Keep the existing service, recovery, route, and URL tests passing; do not build a synthetic signature suite or duplicate every Cashier webhook test.

**Done when:** the real-process recovery and sandbox results are recorded, local billing state is checked, and limitations are documented. If sandbox access is unavailable, leave the sandbox checks open; code tests do not close them.

## Ticket 7 — P4.4: Record a query and response baseline

**Start with:** the released project, task, and meeting listing routes and their query services.

Seed a representative dataset on the intended database. For a small repeatable workload, record query count, slow queries or query plans, bounded page size, and p95 response time. Fix only a demonstrated N+1 or index problem, then rerun the same workload and record the before/after result. Larger-volume load testing can follow launch once real traffic supplies a useful target.

**Done when:** the dataset, workload, baseline numbers, and any measured improvement are recorded, including when no code change is necessary.

## Ticket 8 — Phase 4 sign-off record

**Start with:** the evidence from Tickets 1–7 and the release commit.

Run the focused tests for changed behavior, both CI database jobs, and these final gates:

```bash
composer test
composer stan
composer pint:test
composer audit --locked --no-dev
```

Check `php artisan schedule:list`, the public Cashier webhook route, the running queue worker, the every-minute scheduler, shared Redis locks, failed-job monitoring, and alerts for `unknown` and `manual_review` subscription operations. Confirm `PADDLE_PUBLIC_KEY` and the public HTTPS `CASHIER_WEBHOOK` URL before deployment. Prepare a rollback procedure and a manual-resolution procedure for unresolved subscription operations. Use the platform's existing operational facilities where available.

Save one concise record of the build identifier, environment, commands, outcomes, provider evidence, final states, and unresolved checks. Correct a failing gate before sign-off. Record any known Cashier webhook limitation and the person responsible for manual reconciliation.

**Done when:** every required Phase 4 check has evidence or is explicitly marked open. Do not call the release production-ready while a required staging, provider, or quality gate remains open.

## Scope and next phase

Message-delivery recovery is required here only if the deferred messaging feature is enabled; otherwise keep that feature inaccessible. Engineering case studies and portfolio write-ups can follow launch, but keep the command/result evidence now. Phase 5 separately covers deployment repeatability, health checks, isolated backup restore, recovery, and delivered alerts. Passing Phase 4 does not complete Phase 5.
