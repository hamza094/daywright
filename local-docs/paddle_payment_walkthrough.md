# Paddle payment flow walkthrough

This document explains the payment code currently used by Daywright. It covers the files involved, what each one does, and which tests protect the important behavior.

## The simple picture

Daywright has two different Paddle directions:

```text
User asks Daywright to start checkout
        -> Cashier creates a Paddle pay link
        -> User completes payment on Paddle
        -> Cashier's /paddle/webhook updates the local subscription

User asks Daywright to swap or cancel
        -> Daywright stores one SubscriptionOperation
        -> Daywright sends one Paddle mutation
        -> completed / failed / unknown is stored
        -> unknown operations are checked later by a read-only recovery command
```

Incoming Paddle webhooks are handled by Laravel Cashier. Outgoing swap and cancel calls use the application's `SubscriptionOperation` safety record.

## 1. Routes and middleware

The subscription routes are in `routes/api/v1/users.php`.

The route is a singleton under `/api/v1/users/me/subscription`:

- `POST` calls `SubscriptionController::store()` to start checkout.
- `GET` calls `SubscriptionController::show()` to read local subscription status.
- `PUT/PATCH` calls `SubscriptionController::update()` to swap plans.
- `DELETE` calls `SubscriptionController::destroy()` to cancel.

The checkout route uses:

- the session middleware;
- the idempotency middleware with a user scope;
- the sensitive billing throttle.

The update and cancel routes use:

- authenticated session access;
- the subscription middleware;
- the sensitive billing throttle.

The update and cancel controllers still require an `Idempotency-Key`, but their database operation record is the source of truth. They do not cache an old HTTP response through the idempotency middleware.

Cashier's incoming route is `/paddle/webhook`, named `cashier.webhook`. Paddle must be configured to call this public HTTPS URL. The application does not have a second custom Paddle inbox route.

Relevant files:

- `routes/api/v1/users.php`
- `app/Http/Controllers/Api/V1/SubscriptionController.php`
- `app/Providers/AppServiceProvider.php`
- `config/cashier.php`

## 2. Starting checkout

The user sends `POST /api/v1/users/me/subscription` with a plan such as `monthly` or `yearly`.

### Step 1: request validation

`app/Http/Requests/Api/V1/SubscriptionRequest.php` validates the selected plan and converts the request to its DTO.

### Step 2: controller

`SubscriptionController::store()` receives the `Paddle` interface through method injection. It calls:

```php
$paddle->subscribe($user, $data->plan);
```

The controller returns the pay link to the frontend.

### Step 3: subscription service

`app/Services/Paddle/SubscriptionService.php`:

1. Resolves the configured Paddle plan ID.
2. Checks that the user can subscribe.
3. Calls the Cashier gateway to create the pay link.

The external call is made through `CashierGateway`, so the business service does not contain Cashier HTTP details.

### Step 4: Cashier gateway

`app/Services/Paddle/CashierGateway.php` calls Cashier's:

```php
$user->newSubscription(...)->returnTo(...)->create();
```

It returns the Paddle checkout URL. No `SubscriptionOperation` is needed for checkout link creation because this is not the swap/cancel mutation workflow.

## 3. Paddle completes payment

After the user pays on Paddle, Paddle sends a webhook to Cashier's `/paddle/webhook` route.

Cashier verifies and processes the webhook using its installed package behavior. Cashier updates the local subscription and related billing records according to the events supported by the installed Cashier version.

The application keeps only a small integration check for this route. It does not duplicate Cashier's signature algorithm or every Cashier event test.

The application-level webhook test is:

- `tests/Feature/Api/Webhooks/Paddle/PaddleWebhookTest.php`

Before production, perform one real Paddle sandbox checkout and confirm the local subscription is created or updated correctly.

## 4. Reading subscription status

`SubscriptionController::show()` calls `SubscriptionViewService`.

The view service reads the local Cashier subscription and returns the current application-facing status. This endpoint does not call Paddle on every request. Paddle remains the external source, while the local database provides the normal API read path.

Relevant files:

- `app/Services/Subscription/SubscriptionViewService.php`
- `app/Models/User.php`
- Cashier's local subscription model
- `tests/Feature/Api/V1/Subscriptions/SubscriptionResourceTest.php`

## 5. Swapping a plan

The user sends:

```text
PUT /api/v1/users/me/subscription
Idempotency-Key: a-client-generated-key
plan: yearly
```

### Step 1: controller checks the key

`SubscriptionController::update()` reads the `Idempotency-Key`. If it is missing, the request returns `400`.

It calls:

```php
$paddle->swap($user, $data->plan, $idempotencyKey);
```

### Step 2: service checks for an existing operation

`SubscriptionService::swap()`:

1. Resolves the plan ID.
2. Loads the user's subscription.
3. Hashes the idempotency key.
4. Looks for an existing operation for this user and key.
5. Rejects reuse of the same key for a different action or plan.
6. Validates that the user is subscribed and is not already on the target plan.

If the same request was already accepted, the stored operation is returned. Paddle is not called again.

### Step 3: create the durable operation

`createProcessingOperation()` runs a short database transaction. It:

1. Locks the user row so two billing changes cannot start together.
2. Rechecks the idempotency operation while holding the lock.
3. Rejects another active operation for the same user.
4. Creates one `SubscriptionOperation` in `processing` state.
5. Stores a claim UUID and a five-minute claim lease.
6. Stores `provider_attempted_at` before the external call.

The transaction ends before contacting Paddle. The network request is never inside a retryable database transaction.

### Step 4: make one Paddle call

`callPaddleOnce()` calls:

```php
$this->cashier->swapAndInvoice($subscription, $planId);
```

The service makes this call only for a newly created operation.

### Step 5: save the result

There are three important outcomes:

- Definite success: the operation becomes `completed`.
- Definite Paddle rejection: the operation becomes `failed` and the API returns a safe conflict/error response.
- Network or unknown exception: the operation becomes `unknown`, gets a retry time, and the API returns `202 Accepted`.

The stored claim token and `processing` state prevent an old worker from changing an operation after another worker has taken it over.

### Step 6: controller response

`SubscriptionController::operationResponse()` maps the stored status:

| Operation status                   | HTTP response |
| ---------------------------------- | ------------- |
| `completed`                        | `200`         |
| `processing`, `unknown`, `pending` | `202`         |
| `failed`                           | `409`         |

The response body is created by `SubscriptionOperationResult::toResponseData()` and includes the public operation UUID, type, status, target plan, retry time, and safe message.

## 6. Canceling a subscription

Cancellation follows the same durable flow as swap:

1. `SubscriptionController::destroy()` requires an idempotency key.
2. `SubscriptionService::cancel()` checks the existing operation and validates the current plan.
3. A new `SubscriptionOperation` is created in `processing`.
4. `CashierGateway::cancel()` calls Cashier's subscription cancellation method once.
5. The result becomes `completed`, `failed`, or `unknown`.

The important difference is the local successful result: recovery verifies Paddle's deleted state and cancellation effective date before saving the local subscription as deleted.

## 7. The operation database record

The model is `app/Models/SubscriptionOperation.php`.

The table is created by:

- `database/migrations/2026_09_21_000001_create_subscription_operations_table.php`
- `database/migrations/2026_09_22_074246_add_unique_constraint_to_subscription_operations.php`

Important columns:

| Column                                   | Purpose                                                         |
| ---------------------------------------- | --------------------------------------------------------------- |
| `operation_uuid`                         | Public identifier returned to the client and used by operators. |
| `user_id`                                | Owner of the billing request.                                   |
| `type`                                   | `swap` or `cancel`.                                             |
| `target_plan`                            | Requested plan for a swap.                                      |
| `idempotency_key_hash`                   | Finds a replay without storing the raw key.                     |
| `request_fingerprint`                    | Detects the same key being reused for a different request.      |
| `status`                                 | Current operation state.                                        |
| `claim_token`                            | Identifies the worker allowed to finish the operation.          |
| `claim_expires_at`                       | Allows recovery after a worker disappears.                      |
| `provider_attempted_at`                  | Shows that the external mutation was attempted.                 |
| `available_at`                           | Earliest time for another recovery read.                        |
| `attempts`                               | Number of recovery attempts.                                    |
| `last_error_class` and `last_error_code` | Safe diagnostic information without raw provider messages.      |

The database unique constraint on `(user_id, idempotency_key_hash)` is the final protection against duplicate operation records.

## 8. Recovery of an unknown result

The scheduler runs this command every minute:

```text
subscriptions:recover-operations --limit=25
```

It is registered in `app/Console/Kernel.php`.

### Selection

`app/Actions/Subscription/RecoverSubscriptionOperations.php` selects operations that are:

- `unknown` and past `available_at`; or
- `processing` with an expired claim lease.

It selects only a bounded number and passes each row to `RecoverSubscriptionOperation`.

### One recovery attempt

`app/Actions/Subscription/RecoverSubscriptionOperation.php`:

1. Atomically claims one operation with a new UUID and five-minute lease.
2. Reads the subscription from Paddle using `CashierGateway::getSubscription()`.
3. Does not call swap or cancel again.
4. Verifies that the remote snapshot proves the requested operation.
5. Updates the local subscription and operation together in a short transaction.

For a swap, proof means the remote subscription has the requested plan and is active. For cancellation, proof means the remote subscription is deleted and has a cancellation effective date.

If Paddle cannot be read or the snapshot does not prove success, the operation is returned to `unknown` with bounded backoff. After the maximum attempts it becomes `manual_review`.

## 9. Manual review

The command is:

```text
subscriptions:resolve-operation {operation_uuid} --mark-completed
subscriptions:resolve-operation {operation_uuid} --mark-failed --reference=INC-123
```

The command is implemented by `app/Console/Commands/ResolveSubscriptionOperation.php`.

The business logic is in `app/Actions/Subscription/ResolveSubscriptionOperation.php`.

`--mark-completed` reads Paddle, verifies the exact remote result, locks the operation, updates the local subscription, and marks it completed. `--mark-failed` records an operator ticket/reference and marks the operation failed. Only `manual_review` operations can be resolved.

## 10. The small supporting classes

- `app/Interfaces/Paddle.php`: application-level contract used by the controller.
- `app/Interfaces/Paddle/CashierGatewayInterface.php`: small contract for Cashier actions and read-only recovery lookup.
- `app/DataTransferObjects/Paddle/PaddleSubscriptionSnapshot.php`: safe, typed representation of Paddle lookup data; contains the swap/cancel proof checks.
- `app/DataTransferObjects/Subscription/SubscriptionOperationResult.php`: converts an operation into the API response shape.
- `app/Actions/Subscription/VerifySubscriptionOperation.php`: shared exact-result verification for recovery and manual resolution.
- `app/Enums/Subscription/SubscriptionOperationStatus.php`: allowed states and state transitions.
- `app/Enums/Subscription/SubscriptionOperationType.php`: swap/cancel operation type.
- `app/Enums/Subscription/SubscriptionOperationRecoveryOutcome.php`: recovery result counters.

## 11. Tests a new developer should read first

Start with these tests in this order:

1. `tests/Feature/Api/V1/Subscriptions/SubscriptionManagementTest.php` — API behavior, idempotency header, and operation responses.
2. `tests/Unit/Services/Paddle/SubscriptionServiceTest.php` — validation, one-call behavior, success, failure, and unknown outcomes.
3. `tests/Unit/Actions/Subscription/RecoverSubscriptionOperationTest.php` — claims, backoff, proof checks, and manual review.
4. `tests/Unit/Actions/Subscription/ResolveSubscriptionOperationTest.php` — safe operator resolution.
5. `tests/Feature/Console/Commands/SubscriptionRecoveryCommandsTest.php` — scheduled and manual command behavior.
6. `tests/Unit/Models/SubscriptionOperationTest.php` — model state transitions and scopes.
7. `tests/Feature/Api/Webhooks/Paddle/PaddleWebhookTest.php` — application-level Cashier route/configuration checks.

The tests intentionally do not recreate Cashier's complete signed-webhook and event-processing test suite. Before release, add real Paddle sandbox verification for checkout, payment webhook delivery, swap, and cancellation.

## 12. What happens in common failure cases

### The same request is sent twice

The same idempotency key finds the existing operation. The second request returns its current status. Paddle is not called twice.

### The same key is used for another plan

The request fingerprint does not match, so the request is rejected as an idempotency mismatch.

### Paddle rejects the mutation

The operation becomes `failed`. The API returns a stable failure response. The client may start a new request with a new idempotency key after correcting the problem.

### Paddle accepts the mutation but the network response is lost

The operation becomes `unknown`. The API returns `202`. Recovery reads Paddle and either completes the operation, retries the read, or moves it to manual review. It does not send the mutation again.

### A worker stops during recovery

The five-minute lease expires. A later scheduler run claims the operation with a new token. The old worker cannot overwrite the new worker's result.

### Paddle webhook arrives out of order

Cashier processes it according to its package behavior. The application must monitor local billing state and manually reconcile a mismatch. The native Cashier route is not a custom durable inbox.

## 13. Release checklist for payments

Before release, confirm:

- `PADDLE_PUBLIC_KEY` and Cashier credentials are configured.
- Paddle points to the public HTTPS `/paddle/webhook` URL.
- `php artisan route:list --name=cashier.webhook` shows the expected route.
- `php artisan schedule:list` shows `subscriptions:recover-operations` every minute.
- The full test suite, PHPStan, Pint, and dependency audit pass.
- A Paddle sandbox checkout creates the expected local subscription.
- A sandbox swap and cancellation complete correctly.
- The recovery command and manual-review command are understood by the operator.

This flow provides durable application tracking for outbound swap/cancel mutations while leaving incoming webhook processing to Cashier, which keeps the payment integration small enough to maintain.
