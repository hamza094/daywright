# Phase 4: Production readiness

Release boundary: **before declaring production ready**.

Source: `local-docs/audits/2026-09-06/rest-production-audit.md`, verification limitations, priorities 2-5 and 7, performance guidance, and release-roadmap step 4. Historical findings are in [findings-reference.md](findings-reference.md).

## SWE 1.6 handoff

Implement a reproducible verification setup and execute the scenarios below against the completed phases 1-3. Preserve unrelated changes. This phase is primarily test infrastructure, measurement, and failure verification. Change application code only when new evidence exposes a remaining defect, then add the corresponding regression.

The audit explicitly requires the intended production database engine, shared Redis, real asynchronous workers, p95/query measurements, and worker interruption. Proposed filenames, scenario orchestration, and evidence formats below are implementation elaborations.

Prerequisites: repaired security/idempotency behavior, durable inbox/operation/message recovery, and the chosen API contracts. Record any prerequisite still incomplete; a missing recovery implementation cannot be accepted merely because a test avoids that failure window.

Use a dedicated test/staging database and Redis namespace with non-customer data. Commit fixtures so separate workers/processes can see them. A transaction wrapped around the whole test is not suitable if it hides fixtures from workers.

## P4.1 — Add a separate real-infrastructure test configuration and CI job

**Existing files to modify/review**

- `phpunit.xml:37` — existing synchronous queue configuration; also inspect SQLite/array settings.
- `.github/workflows/tests.yml:49` — existing SQLite-oriented suite execution.
- `composer.json` — existing `test` and `stan` scripts.
- `config/database.php`, `config/cache.php`, `config/queue.php`.
- `.env.example`.
- `tests/TestCase.php` — existing provider request prevention and fake test credentials.

**New (proposed) files**

- `phpunit.integration.xml`.
- `tests/Integration/IntegrationTestCase.php`.
- `tests/Integration/InfrastructureConfigurationTest.php`.
- `tests/Support/Integration/WorkerHarness.php`.
- `docs/PRODUCTION_VERIFICATION.md`.

**Implementation checklist — audit requirement**

- [ ] Execute failure/concurrency tests using the intended production database engine.
- [ ] Use shared Redis for the relevant distributed locks/cache behavior.
- [ ] Use actual asynchronous workers and the intended production queue driver.
- [ ] Integrate this evidence into CI rather than relying on SQLite/synchronous test success.

**Implementation elaboration**

- [ ] Keep the fast unit/feature suite available and add a separate integration suite/job.
- [ ] Configure database, Redis, and queue settings explicitly for both the test process and every child worker/HTTP process.
- [ ] Assert the resolved configuration at runtime: no in-memory SQLite, no array lock store, no synchronous queue for these scenarios.
- [ ] The existing workflow already starts Redis; prove the test paths actually use it.
- [ ] Use uniquely scoped test data and lock/cache prefixes. A worker and its coordinating test must share the same namespace, while independent runs remain isolated.
- [ ] Avoid `Queue::fake()` and in-memory database transactions in tests intended to prove worker behavior.
- [ ] Use a controllable provider stub reachable by child processes, or a provider sandbox where appropriate. In-process HTTP fakes do not automatically configure a separate worker.
- [ ] Synchronize races with explicit barriers/events so the competing requests actually overlap.
- [ ] Add bounded process startup/exit handling and always collect worker logs and terminate the test-owned processes.
- [ ] Document the exact database/Redis/PHP versions, queue connection, worker command, and timeout/retry/lease configuration used.

**Tests and verification**

- [ ] Prove a dispatched job is executed in a distinct process and sees committed fixture data.
- [ ] Prove two processes contend for the same actual Redis lock.
- [ ] Make configuration mismatch fail before behavior tests run.
- [ ] Verify the new CI job fails when one of its required services or tests fails.

Suggested command after the proposed configuration exists:

```sh
php vendor/bin/phpunit --configuration phpunit.integration.xml --do-not-cache-result --testdox
```

This is a future command, not a claim that the configuration already exists.

## P4.2 — Verify concurrent requests and serialized state changes

**Existing implementation under test**

- The Composer-managed idempotency solution from phase 1.
- `app/Services/Zoom/ZoomConnectorManager.php:31`.
- `app/Http/Integrations/Zoom/ZoomConnector.php:32`.
- `app/Traits/HasStateMachine.php:26`.
- `app/Services/Task/TaskService.php:85`.
- `app/Services/Project/ProjectService.php:114`.
- `app/Services/Paddle/SubscriptionService.php:175` and phase 2 operation records.

**New (proposed) tests**

- `tests/Integration/IdempotencyConcurrencyTest.php`.
- `tests/Integration/ZoomRefreshConcurrencyTest.php`.
- `tests/Integration/StateTransitionConcurrencyTest.php`.
- `tests/Integration/SubscriptionOperationConcurrencyTest.php`.

**Checklist**

- [ ] Send simultaneous same-operation requests with one idempotency key; assert the selected replay/in-flight contract and one committed business effect.
- [ ] Repeat with different concrete projects/meetings sharing a key; assert separate execution or explicit conflict according to P1.4.
- [ ] Hold a slow provider response within the configured deadline and show a second process cannot perform duplicate protected work.
- [ ] Exercise the renewal/fencing path if selected; test that a stale owner cannot overwrite a successor's result.
- [ ] Force a request to acquire a lock after another has completed; assert the post-lock completion check prevents re-execution.
- [ ] Race OAuth refresh requests and verify the selected refresh policy and consistent persisted credentials.
- [ ] Race task/project transitions from independently loaded state; assert terminal transitions remain protected.
- [ ] Race updates with stale versions/preconditions; exactly the allowed update succeeds and conflicts follow the published API contract.
- [ ] Race incompatible subscription operations and verify serialization without repeated remote mutation caused by database retry.
- [ ] Query final durable business state and provider-stub call records; HTTP success counts alone are insufficient.

**Verification**

- [ ] Capture evidence that processes overlapped at the intended failure window.
- [ ] Use bounded repeated runs only where needed to establish stability; do not conceal race failures through indiscriminate retries.
- [ ] Link any remaining defect to a focused repair and regression test.

## P4.3 — Verify process interruption, queue outage, and recovery

**Existing implementation under test**

- `app/Http/Middleware/VerifyZoomWebhook.php:59`.
- `app/Services/Webhooks/ZoomWebhookDispatcher.php:18`.
- `app/Actions/Meetings/CreateProjectMeeting.php:40` and `:61`.
- `app/Services/Paddle/SubscriptionService.php:175`.
- `app/Actions/Project/DispatchProjectMessageAction.php:24`, `:84`, and `:111`.
- `app/Models/Message.php:51`.
- `app/Console/Kernel.php` and phase 2 recovery commands.
- `app/Jobs/MailMessage.php` and `app/Jobs/SmsMessage.php`.

**New (proposed) tests**

- `tests/Integration/WebhookRecoveryTest.php`.
- `tests/Integration/MeetingOperationRecoveryTest.php`.
- `tests/Integration/SubscriptionOperationRecoveryTest.php`.
- `tests/Integration/MessageDispatchRecoveryTest.php`.

**Checklist**

- [ ] Disable queue dispatch after durable webhook acceptance, restore it, and prove the pending event is processed by recovery.
- [ ] Interrupt a worker after inbox claim and before completion; restart it or another worker and prove progress resumes.
- [ ] Interrupt meeting creation after the provider stub records success but before local finalization; verify reconciliation finds the same remote operation.
- [ ] Simulate an ambiguous provider timeout; ensure the operation remains unknown until evidence resolves it.
- [ ] Interrupt subscription finalization after provider success; recover without blindly issuing the mutation again.
- [ ] Interrupt message dispatch after claim commit but before queue dispatch; demonstrate expiry/recovery.
- [ ] Drive a real batch to terminal failure; verify the actual failure/finally lifecycle and recipient-specific retry.
- [ ] Preserve confirmed successful recipients during retries and repeated scheduler runs.
- [ ] Demonstrate that old workers/callbacks cannot clear or overwrite newer ownership.
- [ ] For provider limitations that prevent automatic resolution, verify the documented unresolved state and operator procedure instead of asserting unsupported exactly-once delivery.
- [ ] Confirm recovered operations retain useful correlation IDs without revealing secret markers from phase 1 tests.

**Verification**

- [ ] Capture worker process exit/restart, recovery execution, and final database state.
- [ ] Distinguish actual process termination from an exception thrown inside one synchronous test.
- [ ] Record recovery duration and retry counts for each scenario.
- [ ] Ensure the scheduler/reconciler can recover work without depending on the original request process remaining alive.

## P4.4 — Measure p95 latency and query behavior on realistic data

**Existing files to inspect/modify only when measurement justifies a change**

- `app/Services/Dashboard/UserProjectListingService.php:51`.
- `app/Services/Task/TaskService.php:48` and `:161`.
- `app/Services/Project/MeetingService.php:40`.
- `app/Repository/Admin/UserRepository.php:33`.
- `app/Http/Requests/Api/V1/Concerns/InteractsWithApiQueryPagination.php`.
- Database migrations/indexes associated with the measured queries; identify exact targets from query plans.

**New (proposed) artifacts**

- `tests/Performance/ApiReadPerformanceTest.php` for repeatable query-count checks where appropriate.
- `tools/performance/daywright-api-load.js` if selecting a JavaScript load runner; use a different tool/file if the existing environment is better suited.
- `docs/PRODUCTION_VERIFICATION.md` — workload and measurement procedure.
- Evidence directory under `local-docs/audits/2026-09-06/implementation/evidence/`, created when actual runs produce results.

**Implementation checklist — audit requirement**

- [ ] Measure p95 latency and query counts on realistic data using the intended database.
- [ ] Verify pagination, bounded collection sizes, and eager-loading behavior under that dataset.

**Implementation elaboration**

- [ ] Define project/user/task/meeting counts, relationship density, filters, page sizes, and concurrency.
- [ ] Measure at least project listing and nested task listing; include meeting/admin-user collections affected by P3.3.
- [ ] Record dataset, hardware/environment, warm-up, duration, throughput, error rate, and latency percentiles.
- [ ] Choose workload-specific acceptance targets before interpreting the run; the audit supplies no universal millisecond or requests-per-second threshold.
- [ ] Count queries at more than one collection size and inspect execution plans for the slow paths.
- [ ] Repair demonstrated N+1/index/query issues, then rerun the same workload for an attributable before/after comparison.
- [ ] Preserve filtering, sort order, authorization, and page-size bounds when optimizing.

**Verification**

- [ ] Another engineer can reproduce the workload and compare results.
- [ ] Results identify the expected operating envelope and any remaining bottleneck.
- [ ] Do not invent load numbers, suppress failed requests, or claim production measurements from synthetic local results.

If the proposed query-count test is created, run it explicitly using the real-database configuration; it is outside the default unit/feature directories:

```sh
php vendor/bin/phpunit --configuration phpunit.integration.xml --do-not-cache-result --testdox tests/Performance/ApiReadPerformanceTest.php
```

## P4.5 — Record release evidence and engineering case studies

The source audit recommends portfolio evidence for one scope bug, one duplicate-operation bug, and one lost-webhook bug. This is documentation of completed work, not an additional application feature.

**New (proposed) documentation**

- `docs/PRODUCTION_VERIFICATION.md`.
- `local-docs/audits/2026-09-06/implementation/evidence/phase-4-results.md`.
- `local-docs/audits/2026-09-06/implementation/evidence/engineering-case-studies.md`.

**Checklist**

- [ ] Record the commit/working-tree snapshot, environment, commands, test results, load measurements, and known limitations.
- [ ] For the scope, duplicate-operation, and webhook cases, show the original invariant, failing regression, repair decision, and verification.
- [ ] Link actual outputs/logs with sensitive values removed.
- [ ] Clearly label deterministic tests, real-process exercises, provider sandbox runs, and actual production evidence.
- [ ] Record unfinished phase 5 deployment/restore/alert gates separately.

## Phase verification and acceptance

After the integration harness is implemented and its dedicated services are running:

```sh
composer test
composer stan
composer audit --locked --no-dev
php vendor/bin/phpunit --configuration phpunit.integration.xml --do-not-cache-result --testdox
```

Run the selected performance tool using the documented workload rather than an invented placeholder command.

- [ ] Tests prove use of the intended database, shared Redis, and actual workers.
- [ ] Concurrency and interrupted-operation scenarios have durable-state assertions and recorded outcomes.
- [ ] Recovery works through deployed worker/scheduler paths in the verification environment.
- [ ] Performance and query measurements meet the selected workload targets or identify explicit unresolved limits.
- [ ] Evidence is reproducible and contains no fabricated production results.
- [ ] Report changed files, failures repaired, verification outputs, and remaining deployment gates.

Phase 4 supplies runtime evidence; phase 5 still completes repeatable installation, health checks, restore/rollback, and alert delivery.
