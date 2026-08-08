# Rate-Limiting Audit & 3-Layer Refactor Plan (v2 — Phased)

## Executive Summary

Your application has **good foundations** — dedicated limiters for auth endpoints, OAuth, admin reads vs. mutations — but it is **missing two of the three Portkey-style layers entirely** (Layer 1 User Ceiling and Layer 2 Per-Token Ceiling). The existing `throttle:api` limiter is doing double duty as a vague "global" limit but is keyed incorrectly to run **before** authentication resolves, creating silent IP-fallback for every authenticated user.

This revised plan restructures the implementation into **3 incremental phases** ordered by risk, so each phase can be merged, tested, and validated independently.

---

## 1. Current State Assessment

### ✅ What You're Doing Right

| Area                                       | Status       | Details                                                                                                                                                              |
| ------------------------------------------ | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --- | ---------------------------------------------------------------- |
| **Auth endpoint isolation**                | ✅ Solid     | `auth-login`, `auth-register`, `password-email`, `password-reset`, `two-factor`, `verification` all have tight per-minute limits with well-namespaced `->by()` keys. |
| **OAuth throttling**                       | ✅ Good      | `oauth2-socialite` keys by `ip + provider`, preventing cross-provider collisions.                                                                                    |
| **Admin read vs. mutation split**          | ✅ Good      | `admin-api` (60/min) for reads, `admin-mutations` (20/min) for writes — correct layering within the admin domain.                                                    |
| **Idempotency middleware**                 | ✅ Excellent | Applied on all state-mutating writes (`token.store`, `invitation.store`, `messages.store`, `meetings.store/update`, `subscription.store`, etc.).                     |
| **Cache key namespacing (auth endpoints)** | ✅ Good      | Using `sprintf('login                                                                                                                                                | %s  | %s', ...)` style prefixes prevents cross-limiter key collisions. |

### ❌ What's Missing or Broken

| #       | Issue                                                       | Severity    | Details                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| ------- | ----------------------------------------------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **M1**  | **No Layer 1 — User-Level Global Ceiling**                  | 🔴 Critical | There is no limiter that caps a single authenticated user's _total_ traffic across all devices, tokens, and sessions. The `api` limiter (60/min) is the closest, but it runs **before `auth:sanctum`** (see M3), so it silently falls back to IP for every request.                                                                                                                                                                                                                                             |
| **M2**  | **No Layer 2 — Per-Token Ceiling**                          | 🔴 Critical | There is zero rate limiting scoped to individual Sanctum Personal Access Tokens. A runaway bot using one API key can consume the entire user budget (once M1 is fixed) or is only IP-limited today.                                                                                                                                                                                                                                                                                                             |
| **M3**  | **`throttle:api` runs BEFORE auth**                         | 🔴 Critical | In [Kernel.php](file:///c:/Users/Hamza/daywright/app/Http/Kernel.php#L51-L55), the `api` middleware group is `['throttle:api', SubstituteBindings, EnsureFrontendRequestsAreStateful]`. The `throttle:api` limiter calls `$request->user()`, but `EnsureFrontendRequestsAreStateful` (which starts sessions for SPA cookie auth) hasn't run yet, and `auth:sanctum` is applied _later_ in route groups. **Result: `$request->user()` is always `null` at this point → every request is keyed by IP, not user.** |
| **M4**  | **No Layer 3 on `POST /api-tokens`**                        | 🟠 High     | Token creation ([tokens.php](file:///c:/Users/Hamza/daywright/routes/api/v1/tokens.php#L16-L18)) has zero throttle. An attacker with a valid session can create unlimited API keys. This is a sensitive mutation that must be tightly limited.                                                                                                                                                                                                                                                                  |
| **M5**  | **No Layer 3 on `DELETE /api-tokens/{token}`**              | 🟠 High     | Token deletion ([tokens.php](file:///c:/Users/Hamza/daywright/routes/api/v1/tokens.php#L19-L21)) is unthrottled. Mass token revocation should be rate-limited.                                                                                                                                                                                                                                                                                                                                                  |
| **M6**  | **No Layer 3 on `POST /backup/database`**                   | 🟠 High     | Database backup ([admin/v1.php](file:///c:/Users/Hamza/daywright/routes/api/admin/v1.php#L30)) only has `throttle:admin-api` (60/min). This expensive operation needs its own tight limit.                                                                                                                                                                                                                                                                                                                      |
| **M7**  | **No Layer 3 on user-delete / force-delete**                | 🟠 High     | `DELETE /users/{user}` and `DELETE /users/{user}/force` in [users.php](file:///c:/Users/Hamza/daywright/routes/api/v1/users.php#L29-L37) are destructive mutations with no specific throttle.                                                                                                                                                                                                                                                                                                                   |
| **M8**  | **No Layer 3 on `POST /users/{user}/avatar`**               | 🟡 Medium   | File upload endpoint ([users.php](file:///c:/Users/Hamza/daywright/routes/api/v1/users.php#L45-L47)) is expensive (storage, image processing) and lacks a throttle.                                                                                                                                                                                                                                                                                                                                             |
| **M9**  | **Duplicate throttle on verification resend**               | 🟡 Low      | `VerificationController` applies `throttle:6,1` [in constructor](file:///c:/Users/Hamza/daywright/app/Http/Controllers/Api/Auth/VerificationController.php#L21) for `resend`, AND the route already has `throttle:verification` (6/min). These are **two separate throttle buckets** that both fire — not harmful but redundant and confusing.                                                                                                                                                                  |
| **M10** | **Webhook endpoints unthrottled**                           | 🟡 Medium   | [webhooks.php](file:///c:/Users/Hamza/daywright/routes/api/v1/webhooks.php) relies on `VerifyZoomWebhook` for auth but has no rate limit. A replay attack with valid signatures could hammer the endpoint.                                                                                                                                                                                                                                                                                                      |
| **M11** | **`invite-actions` only on `store`, not `accept`/`reject`** | 🟡 Low      | Only invitation creation has `throttle:invite-actions`. Accept and reject ([invitations.php](file:///c:/Users/Hamza/daywright/routes/api/v1/projects/invitations.php#L18-L26)) are unthrottled.                                                                                                                                                                                                                                                                                                                 |
| **M12** | **Subscription create/update unthrottled**                  | 🟡 Medium   | `POST /users/me/subscription` and `PUT` ([users.php](file:///c:/Users/Hamza/daywright/routes/api/v1/users.php#L22-L26)) hit Paddle APIs and are expensive — no throttle applied.                                                                                                                                                                                                                                                                                                                                |

---

## 2. Vulnerability & Collision Risks

### 2.1 The Kernel Ordering Bug (M3) — Full Trace

```
Request arrives
  → Global middleware (TrustProxies, CORS, etc.)
  → 'api' middleware group:
      1. throttle:api          ← $request->user() is NULL here!
      2. SubstituteBindings
      3. EnsureFrontendRequestsAreStateful  ← session starts HERE
  → Route middleware:
      4. auth:sanctum          ← user resolved HERE
      5. tokenAbility:*
```

> [!CAUTION]
> Because `throttle:api` executes at step 1 but `auth:sanctum` runs at step 4, the `api` limiter's `$request->user()?->id` is **always null**. Every API request is keyed by IP address. This means:
>
> - Two different users behind the same NAT/VPN share one 60/min bucket.
> - A single user across 3 different IPs gets 180 req/min instead of 60.
> - The "user-level" intent of the limiter is completely defeated.

### 2.2 Cache Key Collision Risk

The current `api` limiter uses:

```php
Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
```

**No namespace prefix.** If user ID `42` exists and a token ID `42` is used later (for Layer 2), they'd collide. All your auth-specific limiters correctly use prefixed keys (`login|`, `pwd-email|`, etc.) — the `api` limiter is the only one that doesn't.

### 2.3 Webhook Signature Replay Window

[webhooks.php](file:///c:/Users/Hamza/daywright/routes/api/v1/webhooks.php) has no throttle. While `VerifyZoomWebhook` validates authenticity, Zoom's signature includes a timestamp tolerance window. Within that window, a valid signed payload can be replayed at volume. A simple `Limit::perMinute(30)->by('webhook|zoom')` would cap this.

---

## 3. Phased Implementation

### Philosophy Recap

```
┌──────────────────────────────────────────────────────────────┐
│  Layer 1: User Ceiling (200/min per user, IP fallback)       │
│  ┌────────────────────────────────────────────────────────┐   │
│  │  Layer 2: Per-Token Ceiling (60/min per token)         │   │
│  │  ┌──────────────────────────────────────────────────┐  │   │
│  │  │  Layer 3: Mutation Limits (5-20/min per action)  │  │   │
│  │  └──────────────────────────────────────────────────┘  │   │
│  └────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

> [!IMPORTANT]
> **Key invariant:** Layer 2 limit (60/min) **<** Layer 1 limit (200/min). A single token can never exhaust the user's global budget. If a user has 5 API keys each doing 60/min = max 300 theoretical, the global 200/min ceiling prevents any single user from exceeding their budget.

---

### Phase 1: Layer 3 — Sensitive Endpoint Limits + Token Cap + Cleanup (Low Risk)

> **Risk: Low.** All changes are purely additive — adding `throttle:` middleware to specific routes and new `RateLimiter::for()` definitions. No middleware ordering changes. No Kernel changes.

> **Fixes: M4, M5, M6, M7, M8, M9, M10, M11, M12.**

---

#### [MODIFY] [RouteServiceProvider.php](file:///c:/Users/Hamza/daywright/app/Providers/RouteServiceProvider.php)

Add new Layer 3 limiter definitions to `configureRateLimiting()`. These are added **alongside** existing limiters — nothing is removed or changed.

```php
// ──────────────────────────────────────────────────────────
// LAYER 3: Sensitive Endpoint / Mutation Limits
// ──────────────────────────────────────────────────────────

// API Token CRUD — very tight (token creation is high-value)
RateLimiter::for('sensitive-token-mgmt', function (Request $request) {
    $key = $request->user()?->id ?: $request->ip();

    return Limit::perMinute(5)->by('sensitive|token-mgmt|' . $key);
});

// Destructive user operations (delete, force-delete)
RateLimiter::for('sensitive-destructive', function (Request $request) {
    $key = $request->user()?->id ?: $request->ip();

    return Limit::perMinute(5)->by('sensitive|destructive|' . $key);
});

// File upload (avatar) — expensive I/O
RateLimiter::for('sensitive-upload', function (Request $request) {
    $key = $request->user()?->id ?: $request->ip();

    return Limit::perMinute(10)->by('sensitive|upload|' . $key);
});

// Subscription mutations — hits external Paddle API
RateLimiter::for('sensitive-billing', function (Request $request) {
    $key = $request->user()?->id ?: $request->ip();

    return Limit::perMinute(5)->by('sensitive|billing|' . $key);
});

// Database backup — extremely expensive
RateLimiter::for('sensitive-backup', function (Request $request) {
    $key = $request->user()?->id ?: $request->ip();

    return Limit::perHour(3)->by('sensitive|backup|' . $key);
});

// Webhook ingress — global per-source cap
RateLimiter::for('webhook-ingress', function (Request $request) {
    return Limit::perMinute(120)->by('webhook|' . $request->ip());
});
```

---

#### [MODIFY] [CreateApiTokenAction.php](file:///c:/Users/Hamza/daywright/app/Actions/Auth/CreateApiTokenAction.php)

Cap max API tokens per user to 5. Per the [backend guidelines](file:///c:/Users/Hamza/daywright/.ai/guidelines/backend-guidelines.md#L170-L189), this is a domain business rule and belongs in the Action, not the Controller.

Add a guard clause at the top of the `execute()` method:

```php
use App\Exceptions\ApiException; // or a new MaxTokensExceededException

// Inside execute(), before creating the token:
if ($user->tokens()->count() >= 5) {
    throw new MaxTokensExceededException();
}
```

> [!NOTE]
> **Why an Action, not the Controller?** Your backend guidelines state: _"Actions are single-responsibility classes that encapsulate specific business logic operations"_ and controllers must _"not put business logic in controllers"_. The 5-token cap is a domain invariant, not an HTTP concern. If token creation is ever triggered from a job, CLI command, or another service, the cap still enforces.

#### [NEW] MaxTokensExceededException

Create a new exception at `app/Exceptions/MaxTokensExceededException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class MaxTokensExceededException extends ApiException
{
    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return 'max_tokens_exceeded';
    }

    public function publicMessage(): string
    {
        return 'You have reached the maximum limit of 5 API tokens. Please delete an existing token before creating a new one.';
    }
}
```

#### Register in [HandlesApiExceptions.php](file:///c:/Users/Hamza/daywright/app/Exceptions/Traits/HandlesApiExceptions.php)

Add the renderable **before** the generic `HttpException` handler:

```php
$this->renderable(fn (MaxTokensExceededException $e) => ApiErrorFormatter::response(
    $e->publicMessage(),
    $e->status(),
    $e->errorCode(),
));
```

---

#### [MODIFY] [api/v1/tokens.php](file:///c:/Users/Hamza/daywright/routes/api/v1/tokens.php)

Add Layer 3 to token CRUD:

```diff
     Route::post('/', 'store')
-        ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write'])
+        ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:sensitive-token-mgmt', 'tokenAbility:account:write'])
         ->name('store');
     Route::delete('/{token}', 'destroy')
-        ->middleware('tokenAbility:account:write')
+        ->middleware(['throttle:sensitive-token-mgmt', 'tokenAbility:account:write'])
         ->name('destroy');
```

#### [MODIFY] [api/v1/users.php](file:///c:/Users/Hamza/daywright/routes/api/v1/users.php)

Add Layer 3 to destructive user operations, avatar upload, and subscription mutations:

```diff
 Route::apiResource('/users', UserController::class)
     ->except(['store'])
     ->middlewareFor(['index', 'show'], 'tokenAbility:team:read')
-    ->middlewareFor(['update', 'destroy'], 'tokenAbility:team:write');
+    ->middlewareFor(['update'], 'tokenAbility:team:write')
+    ->middlewareFor(['destroy'], ['throttle:sensitive-destructive', 'tokenAbility:team:write']);

 Route::delete('/users/{user}/force', ForceDeleteUserController::class)
-    ->middleware('tokenAbility:team:write')
+    ->middleware(['throttle:sensitive-destructive', 'tokenAbility:team:write'])
     ->name('users.forceDestroy')
     ->withTrashed();
```

```diff
     Route::post('/avatar', [AvatarController::class, 'store'])
-        ->middleware('tokenAbility:team:write')
+        ->middleware(['throttle:sensitive-upload', 'tokenAbility:team:write'])
         ->name('user.avatar');
```

```diff
     Route::singleton('subscription', SubscriptionController::class)
         ->creatable()
         ->middlewareFor('show', 'tokenAbility:account:read')
-        ->middlewareFor('store', [Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write'])
-        ->middlewareFor(['update', 'destroy'], ['subscription', Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:account:write']);
+        ->middlewareFor('store', [Idempotent::using(scope: IdempotencyScope::User), 'throttle:sensitive-billing', 'tokenAbility:account:write'])
+        ->middlewareFor(['update', 'destroy'], ['subscription', Idempotent::using(scope: IdempotencyScope::User), 'throttle:sensitive-billing', 'tokenAbility:account:write']);
```

#### [MODIFY] [api/admin/v1.php](file:///c:/Users/Hamza/daywright/routes/api/admin/v1.php) — Backup

```diff
-    Route::post('/backup/database', [DashboardController::class, 'backup'])->name('backup.database');
+    Route::post('/backup/database', [DashboardController::class, 'backup'])
+        ->middleware('throttle:sensitive-backup')
+        ->name('backup.database');
```

#### [MODIFY] [api/v1/webhooks.php](file:///c:/Users/Hamza/daywright/routes/api/v1/webhooks.php)

```diff
 Route::controller(ZoomWebhookController::class)
-    ->middleware([VerifyZoomWebhook::class, Idempotent::using(scope: IdempotencyScope::Global)])
+    ->middleware([VerifyZoomWebhook::class, 'throttle:webhook-ingress', Idempotent::using(scope: IdempotencyScope::Global)])
     ->prefix('webhooks/zoom/meetings')
```

#### [MODIFY] [api/v1/projects/invitations.php](file:///c:/Users/Hamza/daywright/routes/api/v1/projects/invitations.php) — Accept/Reject

```diff
 Route::post('invitations/accept', AcceptProjectInvitationController::class)
-    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:team:write'])
+    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:invite-actions', 'tokenAbility:team:write'])
     ->name('accept.invitation')

 Route::post('invitations/reject', RejectProjectInvitationController::class)
-    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'tokenAbility:team:write'])
+    ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'throttle:invite-actions', 'tokenAbility:team:write'])
     ->name('reject.invitation')
```

#### [MODIFY] [VerificationController.php](file:///c:/Users/Hamza/daywright/app/Http/Controllers/Api/Auth/VerificationController.php) — Remove duplicate throttle

```diff
 public function __construct(private readonly VerifyEmailService $verifyEmailService)
 {
-    $this->middleware('throttle:6,1')->only('resend');
 }
```

The route-level `throttle:verification` (6/min) already covers `resend`. The constructor-based `throttle:6,1` creates a second, anonymous 6/min bucket — redundant and confusing.

---

#### Phase 1 Verification

```bash
# All existing tests must pass — no behavioral changes to existing rate limits
php artisan test

# Verify new throttle middleware appears on the correct routes
php artisan route:list --columns=method,uri,middleware -v | Select-String "sensitive|webhook-ingress|invite-actions"
```

Manual spot-check:

- Hit `POST /api-tokens` 6 times in 1 minute → expect 429 on request #6.
- Hit `POST /api-tokens` with 5 existing tokens → expect 403 with `max_tokens_exceeded` code.

---

### Phase 2: Layer 1 — User-Level Ceiling & Safety Net (Medium Risk)

> **Risk: Medium.** We avoid changing `Kernel.php` entirely based on your concern. We preserve `throttle:api` as a global IP-based safety net, but increase its limit so it doesn't block legitimate authenticated users. We then apply the strict user-level ceiling inside the route groups.

> **Fixes: M1, M3 (via bypass).**

---

#### [MODIFY] [RouteServiceProvider.php](file:///c:/Users/Hamza/daywright/app/Providers/RouteServiceProvider.php)

Replace the existing `api` limiter and add the new `user-ceiling` limiter:

```diff
-RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
+
+// 1. The Global Safety Net (Runs in Kernel)
+// We keep this at 300/min by IP. Since it runs before auth, it always keys by IP.
+// If we kept this at 60/min, it would block authenticated users before they could reach their 200/min user ceiling.
+RateLimiter::for('api', function (Request $request) {
+    return Limit::perMinute(300)->by('api-safetynet|' . $request->ip());
+});
+
+// 2. The Strict User Ceiling (Layer 1 - Runs after auth)
+RateLimiter::for('user-ceiling', function (Request $request) {
+    $user = $request->user();
+
+    // If unauthenticated, skip (handled by the api safety net)
+    return $user
+        ? Limit::perMinute(200)->by('user-ceiling|' . $user->id)
+        : Limit::none();
+});
```

---

#### [MODIFY] [api/v1.php](file:///c:/Users/Hamza/daywright/routes/api/v1.php)

Apply Layer 1 **after** `auth:sanctum`:

```diff
 // Authenticated Routes
-Route::middleware(['auth:sanctum'])->group(function (): void {
+Route::middleware(['auth:sanctum', 'throttle:user-ceiling'])->group(function (): void {
     require __DIR__.'/v1/users.php';
     require __DIR__.'/v1/tokens.php';
```

#### [MODIFY] [web/v1.php](file:///c:/Users/Hamza/daywright/routes/web/v1.php)

SPA session routes run through the `web` middleware group (not `api`), so they don't get `throttle:api` at all today. After auth, apply Layer 1:

```diff
         Route::post('logout', [SpaAuthController::class, 'logoutSpa'])
             ->name('logout')
-            ->middleware('auth:sanctum');
+            ->middleware(['auth:sanctum', 'throttle:user-ceiling']);
```

The authenticated 2FA routes should also get Layer 1:

```diff
-    Route::middleware('auth:sanctum')->group(function (): void {
+    Route::middleware(['auth:sanctum', 'throttle:user-ceiling'])->group(function (): void {
         Route::post('setup', [TwoFactorController::class, 'prepareTwoFactor'])
```

> [!IMPORTANT]
> **Admin routes are NOT touched.** Admin routes remain isolated with their own `throttle:admin-api` (60/min) and `throttle:admin-mutations` (20/min). If an admin exhausts their global user budget via the regular app, it will not block their admin dashboard access.

---

#### Phase 2 Verification

```bash
# Full test suite
php artisan test
```

Manual checks:

1. **Safety Net test:** Make an unauthenticated `GET /api/v1/scopes` request → confirm it is throttled at 300/min by the IP safety net.
2. **Layer 1 test:** Make 201 requests as an authenticated user within 1 minute → expect 429 on request #201.

---

### Phase 3: Layer 2 — Per-Token Ceiling (Medium Risk)

> **Risk: Medium.** This phase introduces a new rate-limiting concept (per-token) and a custom error response. It's conceptually new but mechanically simple — one new limiter definition and one middleware addition.

> **Fixes: M2.**

---

#### [MODIFY] [RouteServiceProvider.php](file:///c:/Users/Hamza/daywright/app/Providers/RouteServiceProvider.php)

Add the Layer 2 per-token limiter definition:

```php
use App\Exceptions\Support\ApiErrorFormatter;

// ──────────────────────────────────────────────────────────
// LAYER 2: Per-Token Ceiling (3rd-party API keys only)
// ──────────────────────────────────────────────────────────
// Sub-limit for individual Sanctum Personal Access Tokens.
// MUST be < Layer 1 to preserve headroom for the web dashboard.
// SPA/session requests (no token) skip this layer entirely via Limit::none().
RateLimiter::for('per-token', function (Request $request) {
    $token = $request->user()?->currentAccessToken();

    // If the request uses a PAT (not a TransientToken from SPA auth)
    if ($token && ! $token instanceof \Laravel\Sanctum\TransientToken) {
        return Limit::perMinute(60)
            ->by('token:' . $token->id)
            ->response(function (Request $request, array $headers) {
                $retryAfter = (int) ($headers['Retry-After'] ?? $headers['retry-after'] ?? 0);

                $response = ApiErrorFormatter::response(
                    'This specific API key has exceeded its rate limit (60/min).',
                    \Illuminate\Http\Response::HTTP_TOO_MANY_REQUESTS,
                    'token_rate_limited',
                    meta: array_filter([
                        'retry_after_seconds' => $retryAfter,
                    ], fn (int $val): bool => $val > 0)
                );

                return $response->withHeaders($headers);
            });
    }

    // SPA/cookie-based session requests are not sub-limited at Layer 2;
    // they are still bounded by Layer 1's user ceiling.
    return Limit::none();
});
```

> [!NOTE]
> The `ApiErrorFormatter` lives at `App\Exceptions\Support\ApiErrorFormatter` (confirmed at [ApiErrorFormatter.php](file:///c:/Users/Hamza/daywright/app/Exceptions/Support/ApiErrorFormatter.php)). The `->response()` closure ensures per-token 429s return `token_rate_limited` as the error code, distinct from the standard `rate_limited` code that the global Layer 1 limiter produces via the existing `ThrottleRequestsException` handler in [HandlesApiExceptions.php](file:///c:/Users/Hamza/daywright/app/Exceptions/Traits/HandlesApiExceptions.php#L75-L89).

---

#### [MODIFY] [api/v1.php](file:///c:/Users/Hamza/daywright/routes/api/v1.php)

Add `throttle:per-token` alongside the existing `throttle:api` from Phase 2:

```diff
 // Authenticated Routes
-Route::middleware(['auth:sanctum', 'throttle:user-ceiling'])->group(function (): void {
+Route::middleware(['auth:sanctum', 'throttle:user-ceiling', 'throttle:per-token'])->group(function (): void {
     require __DIR__.'/v1/users.php';
     require __DIR__.'/v1/tokens.php';
```

The `throttle:user-ceiling` runs first (Layer 1), then `throttle:per-token` (Layer 2). Both middlewares execute **after** `auth:sanctum`, so `$request->user()` is guaranteed to be resolved.

---

#### Phase 3 Verification

```bash
# Full test suite
php artisan test
```

Manual checks:

1. **Layer 2 test:** Make 61 requests using a PAT within 1 minute → expect 429 on request #61 with `token_rate_limited` error code.
2. **SPA bypass test:** Confirm the same user can still access via SPA session after their token is rate-limited.
3. **Error schema test:** Verify the 429 response body matches the standard `ApiErrorFormatter` JSON shape (`message`, `code`, `errors`, `meta`).

---

## 4. Rate Limit Budget Summary

| Layer  | Limiter Name            | Limit   | Keyed By                       | Applied Where                      | Phase |
| ------ | ----------------------- | ------- | ------------------------------ | ---------------------------------- | ----- |
| **L0** | `api` (Safety Net)      | 300/min | `api-safetynet\|{ip}`          | Global API middleware group        | 2     |
| **L1** | `user-ceiling`          | 200/min | `user-ceiling\|{id}`           | All authenticated route groups     | 2     |
| **L2** | `per-token`             | 60/min  | `token:{id}` (skip for SPA)    | All authenticated API route groups | 3     |
| **L3** | `sensitive-token-mgmt`  | 5/min   | `sensitive\|token-mgmt\|{id}`  | `POST/DELETE /api-tokens`          | 1     |
| **L3** | `sensitive-destructive` | 5/min   | `sensitive\|destructive\|{id}` | `DELETE /users/*`, force-delete    | 1     |
| **L3** | `sensitive-upload`      | 10/min  | `sensitive\|upload\|{id}`      | `POST /users/{user}/avatar`        | 1     |
| **L3** | `sensitive-billing`     | 5/min   | `sensitive\|billing\|{id}`     | Subscription create/update/destroy | 1     |
| **L3** | `sensitive-backup`      | 3/hour  | `sensitive\|backup\|{id}`      | `POST /admin/backup/database`      | 1     |
| **L3** | `webhook-ingress`       | 120/min | `webhook\|{ip}`                | Zoom webhook endpoints             | 1     |
| —      | `admin-api`             | 60/min  | `admin-api\|{id}`              | Admin read routes                  | —     |
| —      | `admin-mutations`       | 20/min  | `admin-mutations\|{id}`        | Admin write routes                 | —     |
| —      | `auth-login`            | 5/min   | `login\|{ip}\|{email}`         | Login                              | —     |
| —      | `auth-register`         | 5/min   | `register\|{ip}`               | Register                           | —     |
| —      | `password-email`        | 4/min   | `pwd-email\|{ip}\|{email}`     | Forgot password                    | —     |
| —      | `password-reset`        | 5/min   | `pwd-reset\|{ip}`              | Reset password                     | —     |
| —      | `two-factor`            | 5/min   | `2fa\|{id}`                    | 2FA endpoints                      | —     |
| —      | `verification`          | 6/min   | `verification\|{id}`           | Email verification                 | —     |
| —      | `invite-actions`        | 10/min  | `invite\|{id}`                 | Invitation send/accept/reject      | —     |
| —      | `oauth2-socialite`      | 8/min   | `oauth\|{ip}\|{provider}`      | OAuth redirect/callback            | —     |

### Headroom Validation

```
User global ceiling:        200 req/min
  └─ Token A ceiling:        60 req/min
  └─ Token B ceiling:        60 req/min
  └─ Token C ceiling:        60 req/min
  └─ Token D ceiling:        60 req/min
  └─ Token E ceiling:        60 req/min
                             ───────────
  Worst case (5 tokens):    300 req/min (theoretical)
  Global ceiling clamps:    200 req/min  ✅ No single user exceeds budget
  SPA headroom:              Always available (L2 skips SPA via Limit::none())
```

---

## User Review Required

> [!IMPORTANT]
> **Rate limit numbers are suggestions.** Review the values in the budget table above and adjust based on your actual traffic patterns. The key architectural constraint is: **Layer 2 × max_tokens_per_user < Layer 1**.

> [!WARNING]
> **Phase 2 keeps `Kernel.php` completely untouched.** The global safety net remains but is increased to avoid blocking the Layer 1 user ceiling.

## Resolved Questions

1. **Max API tokens:** Capped at 5 tokens per user. Enforced in `CreateApiTokenAction` (not the controller) per backend guidelines. Throws `MaxTokensExceededException` with `max_tokens_exceeded` error code.
2. **Distinct error code:** The `per-token` limiter uses a custom response with `ApiErrorFormatter` (at `App\Exceptions\Support\ApiErrorFormatter`) to return `token_rate_limited` on `429`.
3. **Global limiter on admin routes:** Not applied to admin routes. Admin routes remain isolated with `throttle:admin-api` and `throttle:admin-mutations` to ensure Admin Isolation.
4. **`api` limiter name:** The `api` name is preserved for backward compatibility and to prevent breaking existing API consumers.

---

## Phase Summary

| Phase | Risk      | What It Does                                                  | Files Touched                                                                                            |
| ----- | --------- | ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| **1** | 🟢 Low    | Layer 3 sensitive throttles + 5-token cap + duplicate cleanup | `RouteServiceProvider`, 6 route files, `CreateApiTokenAction`, `VerificationController`, 1 new exception |
| **2** | 🟡 Medium | Keep Kernel safety net + add Layer 1 user ceiling             | `RouteServiceProvider`, `api/v1.php`, `web/v1.php`                                                       |
| **3** | 🟡 Medium | Layer 2 per-token ceiling                                     | `RouteServiceProvider`, `api/v1.php`                                                                     |
