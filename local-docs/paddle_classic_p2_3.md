# P2.3 — Make Paddle Classic subscription changes safe

## Goal

Keep Cashier Paddle 1.x/Paddle Classic and make `swap` and `cancel` safe when Paddle succeeds but Daywright loses the response. The current service calls Paddle inside a retryable database transaction, so Laravel can repeat the same provider mutation.

Paddle Billing migration is a separate future project. Do not create a generic integration-operation table or a second webhook inbox.

## Fixed decisions

- `subscribe` only creates a pay link. Keep it synchronous and outside a database transaction.
- `swap` and `cancel` create a durable `SubscriptionOperation` before calling Paddle.
- Every Paddle call happens after the database transaction commits.
- One operation can call Paddle at most once. Never repeat a call after `provider_attempted_at` is set.
- Timeout, connection error, malformed response, process crash, and local-save failure become `unknown`.
- Confirmed Paddle validation/rejection becomes `failed`.
- Recovery performs read-only Paddle requests only.
- Correlate with the Paddle subscription ID and exact remote plan/status. Do not expect Classic to return our operation UUID.
- Keep the current monthly/yearly API values and Free/Pro access rules.
- Configure `CASHIER_WEBHOOK` with the public HTTPS URL ending in `/api/v1/webhooks/paddle`. Cashier uses this explicit URL; the generated Laravel route name is not used.

## Phase 0 — Verify Classic sandbox behavior

Record sanitized examples for pay-link creation, `swapAndInvoice()`, `cancel()`, Classic subscription reads, `subscription_updated`, and `subscription_cancelled`.

Confirm the fields used for Paddle subscription ID, target plan, cancellation state, `alert_id`, and event time. Do not store credentials or raw payloads.

If a read cannot prove a mutation result, recovery must end in `manual_review` instead of sending the mutation again.

## Phase 1 — Durable subscription operations

Create:

- `app/Models/SubscriptionOperation.php`;
- `app/Enums/Subscription/SubscriptionOperationStatus.php`;
- `app/Enums/Subscription/SubscriptionOperationType.php`;
- one migration, factory, and model tests.

Store:

- public operation UUID;
- `user_id`, local subscription ID, and Paddle subscription ID;
- operation type (`swap` or `cancel`) and optional target plan;
- hashed idempotency key and request fingerprint;
- status, attempt count, and `available_at`;
- claim token and five-minute lease expiry;
- `provider_attempted_at`, completed/failed/last-attempt timestamps;
- safe error class and error code only.

Use only these states:

```text
pending → processing
processing → completed | failed | unknown
unknown → processing | manual_review
manual_review → completed | failed
```

Add indexes for user, Paddle subscription ID, recovery fields, and claim expiry. Never store raw keys, headers, signatures, provider responses, or exception messages.

Inside the operation-creation transaction, lock the user and allow only one non-terminal swap/cancel operation. Return `409` when another active operation exists.

## Phase 2 — Refactor subscription mutations

Update `SubscriptionService`, its fake/interface, `SubscriptionController`, `SubscriptionRequest`, and the subscription DTO.

Remove `executeSerially()` and its five-attempt transaction around provider calls. Keep the service as the orchestration boundary and inject one small Cashier gateway for provider calls and read-only subscription lookup.

### Subscribe

1. Validate the plan.
2. Generate the Classic pay link.
3. Return `200` with the pay link.

Do not create a `SubscriptionOperation` for subscribe. HTTP idempotency still applies.

### Swap and cancel

1. Validate the requested transition.
2. Read `Idempotency-Key` into the request DTO.
3. In a short transaction, lock the user and find an identical existing operation.
4. Return that operation for the same key and fingerprint.
5. Return `422` for the same key with different input.
6. Return `409` for another active operation.
7. Create and commit a `pending` operation.
8. Atomically claim it with a UUID token and five-minute lease.
9. Call Paddle outside every database transaction.
10. Mark confirmed provider rejection as `failed`.
11. Save local state and mark `completed`, guarded by the claim token.
12. Mark uncertain results as `unknown` and return `202`.

Use these responses:

- `200`: provider mutation and local save confirmed;
- `202`: result unknown and recovery scheduled;
- `409`: another operation is active;
- `422`: invalid input or idempotency fingerprint mismatch;
- `503`: Paddle unavailable before a provider attempt was made.

Never call Paddle again after `provider_attempted_at` is set.

## Phase 3 — Read-only recovery and manual resolution

Create `RecoverSubscriptionOperation`, `RecoverSubscriptionOperations`, and `ResolveSubscriptionOperation` with focused tests.

Schedule `subscriptions:recover-operations --limit=25` every minute with `onOneServer()` and `withoutOverlapping()`.

Recovery must:

1. Select due `unknown` rows and expired `processing` rows.
2. Claim one row atomically with a token and five-minute lease.
3. Read the exact Paddle subscription by its Paddle subscription ID.
4. Complete a swap only when the remote plan equals `target_plan`.
5. Complete a cancel only when the remote cancellation state matches.
6. Retry reads after 1, 5, 15, and 60 minutes.
7. Move to `manual_review` after five unsuccessful reads.
8. Guard every final update with the claim token.

Commands:

```text
php artisan subscriptions:recover-operations --limit=25
php artisan subscriptions:resolve-operation {operation_uuid} --mark-completed
php artisan subscriptions:resolve-operation {operation_uuid} --mark-failed --reference=INC-123
```

`--mark-completed` must perform one final authoritative read. `--mark-failed` requires an incident/reference value. Remove `--force`; no option may bypass the read or call Paddle.

## Phase 4 — Durable Paddle webhooks

Reuse the existing `webhook_inboxes` table and shared claims, leases, retry, and recovery. Keep Paddle validation and business handling provider-specific.

The Paddle ingress must verify the Classic signature, validate the minimum payload, use `alert_id` as the event key, encrypt the payload, commit the inbox row, and acknowledge only after commit.

The Paddle handler must process supported Classic events, lock the local subscription, ignore duplicates, ignore older event times, retry an update that arrives before subscription creation, and reconcile matching active subscription operations by Paddle subscription ID and exact state.

Do not invoke private Cashier webhook-controller methods. Do not log raw payloads, signatures, or routine success messages. Keep the old Zoom job delegating to the shared processor until queued Zoom jobs are drained.

## Phase 5 — Idempotency and API contract

Extend the idempotency middleware patch to support `DELETE`. Require `Idempotency-Key` on subscribe, swap, and cancel routes, using user scope. Store only its hash in the operation.

Expose only this operation data:

```text
id, type, status, target_plan, available_at
```

The frontend polls the existing subscription endpoint and trusts server state. It must not assume that a successful request means Paddle has already changed.

Regenerate the Scramble/OpenAPI contract for `200`, `202`, `409`, `422`, and `503`.

## Phase 6 — Tests and release gates

Test provider calls outside retryable transactions, one provider attempt per operation, repeated idempotent requests, fingerprint mismatch, concurrent operations, uncertain results, expired claims, stale tokens, exact reconciliation, manual review, safe manual resolution, required DELETE idempotency, signature failures, malformed payloads, duplicate/out-of-order webhooks, inbox failure/recovery, and unchanged access rules.

Run focused SQLite and MySQL tests, then:

```text
composer test
composer stan
composer pint:test
composer rector:test
composer audit --locked --no-dev
php artisan schedule:list
php artisan scramble:export
```

Perform one real Classic sandbox swap and cancellation. Prove convergence using Paddle subscription ID, exact remote plan/status, webhook `alert_id`, and local operation ID. Do not claim recovery where the Classic API cannot prove the result.

## Completion criteria

P2.3 is complete when no Paddle mutation runs inside an automatically retried transaction, every mutation has one durable provider attempt, unknown results never trigger blind repeats, Paddle webhooks are durable and replay-safe, cancellation is idempotent, manual review is audited, and all release gates pass.
