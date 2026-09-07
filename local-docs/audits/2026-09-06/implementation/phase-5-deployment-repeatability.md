# Phase 5: Deployment repeatability

Release boundary: **repeatable deployment and recovery before final production sign-off**.

Source: `local-docs/audits/2026-09-06/rest-production-audit.md`, release-roadmap step 5 and the unverified operational capabilities. Findings and historical evidence are separated into [findings-reference.md](findings-reference.md).

## SWE 1.6 handoff

Implement the deployment/configuration/documentation changes and execute the available staging checks for this phase. Preserve unrelated work. The audit requires PHP/runtime alignment, dependency health checks, restore/rollback exercises, delivered alerts, and PHPUnit metadata migration. Proposed classes, test names, operational procedures, and evidence formats are implementation elaborations.

Prerequisites: phases 1-3 implemented, phase 4 real-infrastructure tests and workload evidence available, and a defined deployment environment. Identify which procedures can be executed locally/staging and which require actual environment access. Do not mark an unexecuted recovery or alert check complete.

Read `composer.json` and the current runtime configuration before editing the deployment guide. Use the actual selected queue driver, service paths, provider integrations, and process supervisor. Store secret names/configuration requirements in documentation, never secret values.

## P5.1 — Align the runtime and make the release procedure reproducible

**Existing files to modify/review**

- `docs/DEPLOYMENT.md:22` — PHP requirement.
- `composer.json` — currently declares `php: ^8.3`.
- `composer.lock`.
- `.github/workflows/tests.yml`.
- `.env.example`.
- `config/queue.php`, `config/cache.php`, `config/database.php`.
- `app/Console/Kernel.php` — actual schedules and retention behavior.

**New (proposed) files, if the deployment target needs them**

- `deploy/supervisor/daywright.conf`.
- `deploy/deploy.sh` for a Linux target; use the target's existing deployment mechanism instead when available.
- `docs/RELEASE_CHECKLIST.md`.

**Implementation checklist — audit requirement**

- [ ] Align deployment instructions with Composer's PHP requirement.
- [ ] Make the documented deployment procedure repeatable for the actual environment.

**Implementation elaboration**

- [ ] Document compatible PHP and required extensions for the resolved dependency set; verify with Composer platform checks.
- [ ] Install from the committed lockfile in deployment, and keep package updates in the tested build/change process.
- [ ] Document required environment variables and the selected database/cache/queue services.
- [ ] Align worker queue names, timeouts, retry_after, operation deadlines, lock leases, and process-manager shutdown bounds with phases 1-2.
- [ ] Include phase 2 reconciliation schedules and verify the scheduler is actually invoked.
- [ ] Document migration, cache/build, application activation, worker restart, health verification, and rollback/forward-recovery ordering.
- [ ] Keep schema and job payload changes compatible with workers/application versions that can overlap during release.
- [ ] Verify every copied command against the installed CLI and actual service configuration.
- [ ] Reconcile deployment-guide schedule/retention claims with `app/Console/Kernel.php` rather than copying historical values.

**Tests to add/update**

- `tests/Unit/Config/QueueConfigTest.php`.
- `tests/Unit/Console/KernelScheduleTest.php`.
- **New (proposed):** `tests/Integration/DeploymentSmokeTest.php`, using the phase 4 configuration/harness where suitable.
- [ ] Assert selected worker/queue/retry bounds are internally consistent.
- [ ] Verify reconciliation schedules are registered with the intended locking behavior.
- [ ] Exercise a normal API read/write and a queued operation after deployment in staging.

**Verification**

- [ ] Follow the guide from a clean build/runtime environment.
- [ ] Confirm the runtime and workers load the intended release.
- [ ] Record the actual commands/environment and any manual prerequisite.

Non-mutating verification commands available in the repository/toolchain:

```sh
php --version
composer check-platform-reqs --no-dev
composer audit --locked --no-dev
php artisan route:list --path=api -vv
php artisan schedule:list
```

## P5.2 — Implement the dependency health checks requested by the guide

**Existing files**

- `docs/DEPLOYMENT.md:408`.
- `app/Console/Kernel.php`.
- `config/database.php`, `config/cache.php`, `config/queue.php`.
- `app/Providers/AppServiceProvider.php` — existing queue/correlation hooks to inspect.

**New (proposed) files**

- `app/Services/Health/DependencyHealthService.php`.
- `app/Console/Commands/CheckDependencies.php`.
- `app/Jobs/Health/RecordWorkerHeartbeat.php`, if choosing a queued heartbeat.
- `config/health.php`, if selected thresholds need central configuration.
- An HTTP health controller/route only if the deployment platform requires one; choose its exact path and exposure policy during implementation.

**Implementation checklist — audit requirement**

- [ ] Implement database connectivity checks.
- [ ] Implement Redis connectivity checks.
- [ ] Implement queue worker status checks.
- [ ] Implement scheduler running-status checks.
- [ ] Replace documentation-only instructions with a working check and operating procedure.

**Implementation elaboration**

- [ ] Use bounded checks and useful non-sensitive component results.
- [ ] Verify worker progress/heartbeat age rather than assuming a queue backend connection proves a worker is running.
- [ ] Verify scheduler execution heartbeat freshness rather than assuming registered schedules prove cron is running.
- [ ] Define failure thresholds and a nonzero command exit status or appropriate health response for the deployment platform.
- [ ] Separate process liveness from dependency readiness where the platform uses both; avoid restart loops caused by misinterpreting a shared dependency outage.
- [ ] Keep check output free of credentials, connection strings, and private payloads.

**Tests to add/update**

- **New (proposed):** `tests/Feature/Console/DependencyHealthCheckTest.php`.
- **New (proposed):** `tests/Integration/DependencyHealthTest.php`.
- `tests/Unit/Console/KernelScheduleTest.php` for the chosen scheduler heartbeat.
- [ ] Healthy database/Redis/current heartbeats produce healthy status.
- [ ] Database outage, Redis outage, stale worker heartbeat, and stale scheduler heartbeat are distinguishable failures.
- [ ] Recovery clears the failed health state.
- [ ] Check duration remains bounded and output contains no fake secret markers.

**Verification**

- [ ] Execute the actual check against staging.
- [ ] Stop a test worker/scheduler or isolate a test dependency and confirm health changes as designed.
- [ ] Record the check command/endpoint and operator interpretation in the guide.

## P5.3 — Prove backup restoration and release recovery

**Existing files**

- `docs/DEPLOYMENT.md` — backup, deployment, and recovery instructions.
- `config/backup.php`.
- `app/Console/Kernel.php` — scheduled backup jobs.
- `config/filesystems.php` — storage locations that need recovery.
- Database migrations added by phases 1-2.

**New (proposed) documentation**

- `docs/RESTORE_RUNBOOK.md`.
- `docs/RELEASE_CHECKLIST.md`.
- `local-docs/audits/2026-09-06/implementation/evidence/restore-and-release-recovery.md`.

**Implementation checklist — audit requirement**

- [ ] Test backup restoration.
- [ ] Test deployment rollback/recovery.
- [ ] Document the repeatable procedure and actual result.

**Implementation elaboration**

- [ ] Define the intended backup scope: database, required files, and the secure recovery of encryption keys needed to read restored encrypted fields.
- [ ] Restore an actual backup into an isolated target and verify schema, representative business records, relationships, and file access.
- [ ] Keep restored jobs/provider integrations controlled so a recovery exercise cannot resend historical customer messages or billing operations.
- [ ] Test access to encrypted meeting data without exposing its secrets in evidence.
- [ ] Record recovery duration and the backup age/data-loss window.
- [ ] Exercise application rollback or forward recovery with a representative schema/worker change.
- [ ] Verify old/new code and queued payload compatibility; document when reversing a migration would lose data and use a forward recovery procedure instead.
- [ ] Include recovery of pending inbox/outbox/operation records and avoid duplicate external effects after restoring.

**Tests and verification**

- Reuse `tests/Integration/DeploymentSmokeTest.php` if created in P5.1, adapted to a controlled restored environment.
- [ ] Smoke-test authentication, representative authorized reads/writes, file access, and controlled queued work after restoration.
- [ ] Confirm health checks pass after release recovery.
- [ ] Record exact backup identifier, target environment, steps, elapsed time, and validation outcome with secrets removed.
- [ ] A backup file's existence or a successful backup command alone does not complete this task.

## P5.4 — Verify alerts reach an operator and support recovery

**Existing files**

- `app/Providers/AppServiceProvider.php` — global queue failure/reporting hooks.
- `config/logging.php`.
- `app/Exceptions/Handler.php`.
- `docs/DEPLOYMENT.md`.
- `app/Console/Kernel.php`.
- Phase 2 operation recovery and phase 5 health-check implementation.

**New (proposed) documentation/configuration**

- `docs/OPERATIONS_RUNBOOK.md`.
- Monitoring/alert configuration under the deployment target's existing configuration directory; select exact files after identifying that platform.
- `local-docs/audits/2026-09-06/implementation/evidence/alert-delivery.md`.

**Implementation checklist — audit requirement**

- [ ] Verify operational alerts reach an operator.
- [ ] Keep a repeatable procedure for alert delivery and response.

**Implementation elaboration**

- [ ] Identify the configured error-reporting/monitoring destination and responsible operator.
- [ ] Exercise a controlled terminal queue failure, stale processing/reconciliation backlog, and a dependency health failure.
- [ ] Confirm actual destination receipt and useful request/job/operation correlation.
- [ ] Document how to locate affected work, distinguish pending/unknown/failed outcomes, and invoke the correct recovery path.
- [ ] Apply phase 1 sanitization to notification/reporting context as well as local logs.
- [ ] Record deduplication/threshold decisions that prevent repeated failures from overwhelming the recipient.

**Tests to add/update**

- `tests/Feature/Jobs/GlobalQueueFailingListenerTest.php`.
- `tests/Feature/Exceptions/HandlerReportingTest.php`.
- New health tests from P5.2.
- [ ] Unit/feature tests verify the reporting payload and context.
- [ ] A controlled staging exercise verifies end-to-end delivery; a mocked reporter is not delivery evidence.
- [ ] Record destination receipt and the operator procedure without publishing addresses/secrets unnecessarily.

## P5.5 — Migrate deprecated PHPUnit metadata without losing coverage

**Existing files**

- `phpunit.xml`.
- `composer.json` and `composer.lock` — inspect the installed PHPUnit version before choosing attribute/config syntax.
- `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php` — one existing docblock-metadata example.
- Other affected files under `tests/`, located using the command below.
- `.github/workflows/tests.yml`.

**Implementation checklist — audit requirement**

- [ ] Migrate deprecated PHPUnit metadata reported by the suite.
- [ ] Preserve the discovered tests and dataset behavior.

**Implementation elaboration**

- [ ] Inventory the actual deprecation output and docblock metadata; do not delete documentation comments indiscriminately.
- [ ] Replace test/data-provider/group/dependency metadata with supported PHPUnit attributes where applicable.
- [ ] Update configuration schema only as required by the installed version and actual reported warnings.
- [ ] Capture test discovery/dataset counts before and after migration; explain intentional changes.
- [ ] Keep the new phase 4 integration suite's configuration consistent with its test runner.
- [ ] Avoid changing application behavior or adding broad suppression merely to hide warnings.

Useful inventory command:

```sh
rg -n '@(test|dataProvider|depends|group|covers|uses|requires)\b' tests
```

**Tests and verification**

- [ ] Run the same full suite before and after the metadata-only change.
- [ ] Confirm no tests or data-provider cases disappeared.
- [ ] Confirm the migrated metadata deprecations are resolved and investigate any remaining runner warnings separately.
- [ ] Run the dedicated integration suite if it uses affected metadata/configuration.

Suggested runner checks after confirming options against the installed PHPUnit:

```sh
php vendor/bin/phpunit --list-tests
php vendor/bin/phpunit --do-not-cache-result --display-phpunit-deprecations
```

## Phase verification and acceptance

- [ ] A clean environment satisfies Composer platform requirements and follows the documented release procedure.
- [ ] Worker/scheduler configuration matches the actual application and phase 2 recovery behavior.
- [ ] Database, Redis, worker, and scheduler health failures are detectable and recover correctly.
- [ ] An actual isolated backup restoration and release recovery have recorded evidence.
- [ ] A controlled alert reached its configured destination and has a usable response procedure.
- [ ] PHPUnit metadata deprecations are resolved without losing tests/datasets.
- [ ] Existing tests, configured static analysis, dependency audit, and the phase 4 integration checks pass for the release candidate.
- [ ] Report changed files, commands/results, evidence paths, any unavailable environment-dependent checks, and unresolved acceptance items.

Only mark exercised checks complete. If staging, backup access, or alert-destination access is unavailable, complete the implementation/documentation and record those specific verification gaps for execution in the proper environment.
