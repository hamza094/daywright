# Production release scope

This document defines what must be complete before the first DayWright production release and what can follow after launch.

## Required before release

- Phase 2: P2.1 durable webhooks, P2.2 Zoom creation recovery, P2.3 Paddle Classic mutation safety, and P2.5 task/project transition serialization.
- Phase 3: P3.1–P3.6 API behavior, error responses, pagination, route registration, infrastructure error mapping, and generated contract synchronization.
- Phase 4: P4.1 real database/Redis/worker test setup, P4.2 concurrency verification, P4.3 interruption and recovery verification, and a P4.4 query-count/index baseline for released endpoints.
- Phase 5: P5.1 reproducible deployment, P5.2 dependency health checks, P5.3 isolated backup restore and release recovery, and P5.4 delivered alerts with an operator runbook.

P2.5 is required before exposing collaborative task/project updates to production users.

## Deferred after release

- P3.7 resource and endpoint naming improvements. Existing public names remain stable until a versioned compatibility change is planned.
- P2.4 message delivery recovery. Implement it immediately before enabling the unreleased messaging feature; do not expose messaging while it remains deferred.
- P4.5 engineering case studies and portfolio evidence. Keep the actual runtime test results and operational evidence for release; the narrative case-study documents can follow.
- P5.5 PHPUnit metadata cleanup when warnings are non-blocking and test discovery is verified. Complete it earlier if warnings fail CI or tests disappear.
- Full high-volume P4.4 load testing after launch, when real traffic and dataset sizes are available. The pre-release query baseline remains required.
- Paddle Billing/Cashier 2.x migration. Launch on the hardened Classic integration and plan Billing migration separately.

Deferred work must not hide a security, reliability, data-integrity, backup, alerting, or API-contract failure. Revisit it after release as maintenance work.

Before enabling messaging, persist dispatch intent with the business change, give message claims an expiring owner, recover abandoned claims, and record each recipient's outcome so successful recipients are not resent. Verify a worker crash after claiming work and a mixed success/failure batch. Until this is complete, keep messaging routes and jobs inaccessible to production users.

## Release decision

Do not call the app production-ready from passing unit and feature tests alone. The release candidate must pass the required Phase 4 and Phase 5 checks in a staging environment that uses the intended database, Redis, queues, scheduler, backups, and alert destination.
