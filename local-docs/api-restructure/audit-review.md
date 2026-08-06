Daywright API Security Audit Report
Audit Scope: Last 6 commits (2fdf559..3bcc895) — API Key Scopes, Middleware, Token Pipeline, and Notifications Auditor Persona: Principal Security Engineer & Senior Laravel Architect Date: 2026-08-04

1. Executive Security Rating
   ✅ PASS WITH WARNINGS
   The implementation is architecturally sound and production-safe. The two-tier auth model is correctly designed and the custom CheckTokenAbilities middleware is an excellent solution to the SPA/Token duality problem. However, there are two MEDIUM and two LOW severity items that should be addressed before public API key documentation goes live.

2. Architecture & Standards Evaluation
   Two-Tier Auth Design: ✅ Excellent
   The 6 commits implement a clean, industry-aligned multi-tier authentication architecture:

Auth Channel Guard Token Type Abilities Scope Enforcement
SPA Web App web (session cookie) TransientToken Implicitly passes all tokenCan() Bypassed by
CheckTokenAbilities
— ✅ Correct
1st-Party Mobile auth:sanctum (Bearer) PAT with ['*'] Wildcard — passes all checks Passes CheckAbilities — ✅ Correct
3rd-Party API Keys auth:sanctum (Bearer) PAT with scoped abilities User-selected from ApiScope enum Enforced by tokenAbility: middleware — ✅ Correct
Key Architectural Decisions — Evaluated
Custom tokenAbility middleware over Sanctum's built-in ability/abilities
TIP

This is the strongest architectural decision in the entire implementation. The custom
CheckTokenAbilities
middleware (line 27: if ($request->bearerToken())) elegantly solves the core Sanctum duality problem: SPA sessions use TransientToken which implicitly passes tokenCan(), but Sanctum's built-in CheckAbilities middleware would block session-authenticated requests because TransientToken doesn't carry explicit abilities.

By checking $request->bearerToken() first, the middleware:

✅ Lets SPA session requests pass through to Layer 2 (policies) unchecked
✅ Enforces scope validation for all Bearer token requests (mobile + API keys)
✅ Avoids the common Sanctum trap of accidentally blocking your own SPA
['*'] for login-issued mobile tokens
This is compliant with OAuth2 first-party client patterns and Sanctum best practices. The login endpoint (POST /login) issues tokens equivalent to a session — the user is the resource owner. Scope restrictions are for delegated access (3rd-party API keys), not direct user authentication.

Scope enforcement ordering: tokenAbility: → can: (Policy)
Correctly implements defense-in-depth. Layer 1 (scopes) is a coarse-grained gate; Layer 2 (policies) enforces fine-grained resource ownership. A token with projects:read can only read projects the user has access to, not all projects in the system.

3. Identified Vulnerabilities & Edge Cases
   🟡 MEDIUM-1: Zoom Webhook Routes Apply tokenAbility:webhooks:write — Breaks Inbound Webhooks
   File:
   webhooks.php
   Severity: MEDIUM Impact: Zoom webhook delivery will fail with 403 because Zoom does not send a Sanctum Bearer token.

The Zoom webhook routes currently apply tokenAbility:webhooks:write:

php

Route::controller(ZoomWebhookController::class)
->middleware([VerifyZoomWebhook::class, Idempotent::using(scope: IdempotencyScope::Global), 'tokenAbility:webhooks:write'])
Zoom sends requests with its own signature header (x-zm-signature), not a Sanctum Authorization: Bearer header. The CheckTokenAbilities middleware checks $request->bearerToken() — if Zoom doesn't send one, the middleware passes through (which is safe). However, if these webhook routes sit inside the auth:sanctum middleware group in
v1.php
, the auth:sanctum middleware will reject the request before tokenAbility even runs.

Remediation: Verify that webhook routes are excluded from the auth:sanctum group. If they are already excluded, tokenAbility:webhooks:write is dead code on these routes and should be removed for clarity:

diff

Route::controller(ZoomWebhookController::class)

- ->middleware([VerifyZoomWebhook::class, Idempotent::using(scope: IdempotencyScope::Global), 'tokenAbility:webhooks:write'])

* ->middleware([VerifyZoomWebhook::class, Idempotent::using(scope: IdempotencyScope::Global)])
  🟡 MEDIUM-2: Notification Dispatched Inside DB::transaction — Silent Failure Risk
  File:
  CreateApiTokenAction.php#L44
  File:
  RevokeApiTokenAction.php#L42
  Severity: MEDIUM Impact: If the queue driver is database, the notification job is written to the jobs table inside the same transaction. If the transaction rolls back (e.g., due to a plan limit exception in the create path), the notification job is rolled back too — which is correct. However, if using redis or sqs, the job is dispatched immediately to the external queue before the transaction commits. A rollback after dispatch means the user receives a "key created" email for a token that doesn't exist.

Remediation: Dispatch notifications after the transaction commits:

diff

// In CreateApiTokenAction::execute()
return DB::transaction(function () use ($user, $name, $scopes, $expiresAt): NewAccessToken {
     $token = $this->apiTokenService->createForUser($user, $name, $scopes, $expiresAt);

     $this->auditLogService->log(/* ... */);

- $user->notify(new ApiKeyCreatedNotification($name));
-     return $token;
  });

* +$user->notify(new ApiKeyCreatedNotification($name));
  Or, use Laravel's afterCommit on the notification class:

php

class ApiKeyCreatedNotification extends Notification implements ShouldQueue
{
use Queueable;
public $afterCommit = true; // Ensures job dispatches only after DB commit
// ...
}
The same pattern applies to RevokeApiTokenAction.

🟢 LOW-1: TokenCreateData DTO Uses scopes Naming but CreateApiTokenAction Also Uses scopes — Consistent but Diverges from Sanctum Terminology
File:
TokenCreateData.php
Severity: LOW (cosmetic / maintainability) Impact: None currently. Sanctum internally calls these abilities. The codebase uses scopes in the request layer, DTO, action, and service, which is consistent. The TokenResource serializes them as abilities (line 46). This asymmetry is a minor readability issue for new developers but causes no bugs.

Recommendation: No action needed for v1. If you standardize later, pick one term (scopes in your domain, abilities in Sanctum's) and stick to it across all layers.

🟢 LOW-2: ApiScope::allValid() Is Defined but Never Called
File:
ApiScope.php#L30-L35
Severity: LOW Impact: None. The method is tested in ApiScopeTest but is not used anywhere in production code — validation is handled by Rule::in(ApiScope::values()) in
UserTokenRequest.php#L54
. The method is harmless dead code, but it could mislead future developers into thinking it's the validation path.

Recommendation: Keep it if you plan to use it in programmatic/service-layer validation. Otherwise, remove it to reduce surface area.

4. Detailed Security Analysis
   4.1 Token Leakage Analysis: ✅ PASS
   Vector Status Evidence
   Plain-text token in DB ✅ Safe Sanctum hashes tokens with SHA-256 before storage. Only the hash is in personal*access_tokens.token.
   Plain-text token in audit logs ✅ Safe
   CreateApiTokenAction
   logs token_id and token_name, never the plain-text value.
   Plain-text token in notifications ✅ Safe
   ApiKeyCreatedNotification
   only includes tokenName, not the token value.
   Plain-text token in API response ✅ Safe
   TokenStoreResource
   returns the plainTextToken only once, at creation. Subsequent GET /api-tokens via TokenResource does not include it.
   Token prefix for secret scanning ✅ Configured dw_live* prefix in
   sanctum.php#L31
   enables GitHub/GitLab secret scanning.
   4.2 Header Conflict (Cookie + Bearer Token): ✅ SAFE
   When a request carries both a valid session cookie AND a Bearer token, Sanctum's Guard checks the Bearer token first (via getTokenFromRequest()). If a valid PAT is found, it authenticates as a token-based request with that token's abilities. The session cookie is ignored.

This means:

A scoped API key (projects:read) + active SPA session = scoped access (the token wins)
The custom CheckTokenAbilities middleware correctly detects $request->bearerToken() and enforces scopes
No privilege escalation is possible through header stacking.

4.3 Privilege Escalation via Scope Selection: ⚠️ ACCEPTABLE RISK
A user can request any scope from the ApiScope enum (e.g., webhooks:write) regardless of their subscription tier or role. The validation in
UserTokenRequest
only checks that the scope string is a valid enum value — it does not check whether the user's plan permits that scope.

Current mitigations:

Layer 2 policies still enforce resource ownership at the controller level
Plan limits are enforced on the number of tokens via PlanLimitService::executeWithinAccountLimit()
The user can only access resources they own — a projects:write scope doesn't grant access to projects they're not a member of
Verdict: This is an acceptable design for v1. Scope-to-plan mapping is a feature enhancement, not a security vulnerability, because policies are the true authorization boundary.

4.4 Immediate Revocation: ✅ PASS
Token deletion in
ApiTokenService::deleteForUser()
performs a hard DELETE on the personal_access_tokens row. Sanctum validates tokens on every request by querying the database and comparing hashes. A deleted token will fail lookup on the next request.

There is no in-memory token cache to invalidate. Revocation is effectively immediate.

4.5 Self-Deletion Prevention: ✅ PASS
ApiTokenService::deleteForUser()
correctly prevents deleting the token currently being used for authentication:

php

if ($currentToken->id === $tokenId) {
throw new AccessDeniedHttpException('Cannot delete the current session token via this route.');
} 5. Production Readiness Checklist
Concern Status Evidence
Token prefix for leak detection ✅ Ready dw*live* in
sanctum.php
Scope enum as single source of truth ✅ Ready
ApiScope.php
— 7 scopes, label(), description(), toArray() for frontend
Request validation against enum ✅ Ready Rule::in(ApiScope::values()) in
UserTokenRequest
No silent fallback to ['*'] ✅ Ready
TokenCreateData::fromArray()
throws InvalidArgumentException if scopes are empty or missing
Rate limiting on token creation ✅ Ready Route group applies throttle:api + idempotency middleware
Idempotency on token creation ✅ Ready Idempotent::using(scope: IdempotencyScope::User) on POST in
tokens.php
Audit trail for create/revoke ✅ Ready security.api_token_created and security.api_token_revoked events in actions
Notifications queued ✅ Ready Both notifications implement ShouldQueue
Scope failures return clean 403 ✅ Ready Sanctum's CheckAbilities returns { "message": "Invalid ability provided." } with 403
SPA session bypass ✅ Ready Custom
CheckTokenAbilities
only enforces for Bearer tokens
Login tokens keep ['*'] ✅ Correct
LoginUserService::createApiToken()
defaults to ['*'] — appropriate for 1st-party login
Test coverage ✅ Good ScopeMiddlewareTest, ApiKeyNotificationTest, ApiScopeTest, updated IdempotencyContractTest, UserTokenTest, PlanLimitServiceFeatureTest 6. Actionable Remediation Summary

# Severity Item Action Required

M-1 🟡 MEDIUM tokenAbility:webhooks:write on Zoom webhook routes Verify routes are outside auth:sanctum; if so, remove the dead middleware
M-2 🟡 MEDIUM Notifications dispatched inside DB::transaction Add $afterCommit = true to both notification classes
L-1 🟢 LOW scopes vs abilities naming inconsistency No action for v1
L-2 🟢 LOW ApiScope::allValid() is unused dead code Remove or keep for future use
Remediation Code for M-2 (Recommended Fix)
Apply to both
ApiKeyCreatedNotification.php
and
ApiKeyRevokedNotification.php
:

diff

class ApiKeyCreatedNotification extends Notification implements ShouldQueue
{
use Queueable;

- public $afterCommit = true;
-      public function __construct(
           private readonly string $tokenName,
       ) {}
  diff

class ApiKeyRevokedNotification extends Notification implements ShouldQueue
{
use Queueable;

- public $afterCommit = true;
-      public function __construct(
           private readonly string $tokenName,
       ) {}
  This ensures notification jobs are only dispatched to the queue after the wrapping DB::transaction() commits successfully. If the transaction rolls back (e.g., plan limit exceeded), no notification is sent.
