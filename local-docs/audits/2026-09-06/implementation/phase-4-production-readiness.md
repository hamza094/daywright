# Phase 4: Production readiness

This phase proves the release code works with the services it will use. Keep the fast SQLite suite. Reuse the existing MySQL CI setup and deployment tools where suitable; add a separate test harness only when a required scenario cannot be run with them.

## P4.1: real services

Run the release candidate against the intended database engine, shared Redis, the chosen queue driver, a real worker, and the scheduler. Confirm the resolved configuration in both the request and worker processes; `sync` queues, array cache, and in-memory SQLite cannot prove these cases. Use isolated non-customer data and committed fixtures visible to both processes. Record the versions and commands used. Make at least the database and Redis checks repeatable in CI; worker exercises may run in staging if CI cannot host them reliably.

## P4.2: simultaneous work

Run a few controlled two-process races, using a barrier so they actually overlap:

- Same idempotency key for one request: one business effect and the documented replay/in-progress response.
- Two stale task/project updates: the allowed update wins and the other receives the published conflict response.
- Two incompatible subscription changes: at most one Paddle mutation attempt for an operation.
- Two recovery workers claiming the same Zoom inbox or subscription operation: only the owner completes it.

Check final database state and provider-stub call counts. Add a regression only for a failure that the existing focused tests do not cover. Include an OAuth token-refresh race if that path is used in the first release and has not already been verified.

## P4.3: interruption and recovery

With a real worker and scheduler, prove these release paths recover after a process or dispatch failure:

1. Zoom webhook accepted in the database, initial queue dispatch unavailable, later recovered.
2. Worker stopped after taking a Zoom inbox claim; another worker resumes after the lease.
3. Zoom create response lost after the provider accepted it; the local meeting stays unknown until lookup or manual review. No second create is sent.
4. Paddle swap/cancel response lost; scheduled recovery reads Paddle and does not repeat the mutation.

Confirm in a real Zoom sandbox that the creation request and webhook carry the same operation ID before relying on webhook recovery. Run a Paddle Classic sandbox checkout/payment, swap, and cancellation before releasing billing. If a provider cannot prove an ambiguous result, verify the operation remains visible for manual review. Message delivery recovery belongs here only when the deferred messaging feature is enabled. Record the worker exit, recovery command, final database state, and any manual-review outcome. A synchronous exception test alone does not prove process recovery.

## P4.4: query and response baseline

For released project/task/meeting listings, use a representative seeded dataset on the intended database. Record query count, slow queries or query plans, bounded page size, and p95 response time for a small repeatable workload. Fix only demonstrated N+1 or index problems, then rerun the same workload. Larger load testing can follow launch once real usage gives a meaningful target.

## Phase 4.5 - Production release checks

Complete these checks in staging before production. Local mocks are useful for code tests, but they do not prove provider, worker, scheduler, or network behavior.

### Paddle sandbox

- Perform a real subscription creation.
- Perform a real subscription swap.
- Perform a real cancellation.
- Verify a successful payment webhook.
- Verify a failed payment webhook.
- Repeat a request with the same idempotency key and confirm one provider mutation.
- Simulate an uncertain provider response and confirm recovery reads Paddle without sending the mutation again.
- Capture the actual Paddle Classic cancellation response and verify that `PaddleSubscriptionSnapshot::provesCancelSucceeded()` checks the correct fields.

### Webhook configuration

- Confirm `/paddle/webhook` is publicly reachable over HTTPS.
- Set `PADDLE_PUBLIC_KEY` in production.
- Configure Paddle to send the required events.
- Confirm Cashier's route and signature handling are active.

Cashier owns the standard webhook behavior. Paddle webhooks can be delivered more than once or out of order, so the production smoke test must verify that local subscription state remains safe.

### Workers and scheduler

- Confirm a queue worker is running.
- Confirm the scheduler runs every minute.
- Confirm Redis is available for `onOneServer()` and `withoutOverlapping()`.
- Confirm failed jobs are monitored.
- Alert on `unknown` and `manual_review` subscription operations.

### Final quality gates

Run:

```bash
composer test
composer stan
composer pint:test
composer audit --locked --no-dev
```

Prepare a rollback procedure and a manual-resolution procedure for unresolved subscription operations.

### Existing code checks

- Keep the existing service and recovery tests passing. They now cover an in-progress replay, stale recovery claim, unknown result, exact remote match, and bounded backoff. Do not expand into a large test matrix unless a new failure requires it.
- Keep the route and configured URL tests. Do not build a synthetic RSA/signature suite or test every Cashier webhook event. That would duplicate package behavior. Verify one real Paddle Classic sandbox checkout/payment and observe that the webhook creates the local subscription, receipt, and safe audit entry. Also exercise a sandbox swap and cancellation, including one ambiguous-response recovery if practical.
- Run the focused tests, normal CI quality gates, and the production-like database job. Fix any current failure before release. Check `php artisan route:list --name=cashier.webhook` and `php artisan schedule:list`.
- Before each deployment, confirm `PADDLE_PUBLIC_KEY` is configured and `CASHIER_WEBHOOK` is the public HTTPS `/paddle/webhook` URL configured in Paddle. Check for unfinished historical Paddle inbox rows; do not delete them.
- The backend guideline now records the narrow Cashier Classic exception. Update `paddle_plan.md` and `paddle_classic_p2_3.md` before release. Record that Cashier can acknowledge an update/cancellation before the local subscription exists and does not order old versus new updates. Monitor and manually reconcile billing mismatches. Do not claim the native route has inbox durability.

Release when the code checks pass, the sandbox flow matches the local subscription state, and the known Cashier webhook limitations are documented and accepted. Future enhancements can be considered from production evidence; they are not part of P2.3.

## Deferred

P4.6 engineering case studies and portfolio write-ups can follow launch. Keep the short command/result records needed for release review now.

## Acceptance

Run the normal tests, static analysis, dependency audit, and the real-service scenarios above. Save one concise evidence record with environment, commands, outcomes, and unresolved failures. Do not label local fakes or unrun sandbox flows as production evidence. Phase 5 still needs deployment, restore, health, and alert checks.
