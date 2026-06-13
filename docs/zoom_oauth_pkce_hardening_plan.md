# Plan: Harden Zoom OAuth State + PKCE Flow

## Purpose

Refactor the existing Laravel + Saloon Zoom OAuth flow so it is production-safe, easier to understand, and easier to test.

The current implementation already has:

```text
PKCE verifier generation
S256 code challenge
state generation
short-lived cache storage
single-use verifier consumption
callback lock
code_verifier sent to Zoom token endpoint
access/refresh token persistence
```

The main remaining issues are:

```text
authorization flow is not bound to the initiating user
state and verifier are stored separately
state validation and verifier consumption are fragmented
state-to-itself comparison is redundant
Saloon expectedState comparison is currently meaningless
OAuth callback error handling is incomplete
ZoomOAuthService dependency is nullable even though it is required
tests need stronger coverage
```

Do not rewrite unrelated Zoom meeting, webhook, connector, rate limit, DTO, or token refresh logic.

---

## Main Files To Inspect First

```text
app/Http/Controllers/Api/OAuth/ZoomAuthController.php
app/Services/Zoom/ZoomAuthorizationStateStore.php
app/Services/Zoom/ZoomOAuthService.php

app/DataTransferObjects/Zoom/AuthorizationRedirectDetails.php
app/DataTransferObjects/Zoom/AuthorizationCallbackDetails.php
app/DataTransferObjects/Zoom/AccessTokenDetails.php

app/Http/Integrations/Zoom/ZoomConnector.php
app/Http/Integrations/Zoom/Requests/GetAccessTokenRequest.php
app/Http/Integrations/Zoom/Requests/GetRefreshTokenRequest.php

app/Repository/OAuthConnectionRepository.php
app/DataTransferObjects/OAuth/OAuthTokens.php

routes containing Zoom OAuth redirect/callback routes
config/services.php
tests covering Zoom OAuth/state/PKCE/token exchange
```

---

# Phase 1: Baseline Review and Characterization Tests

Before changing production code:

1. Inspect current redirect/callback routes and authentication middleware.
2. Confirm callback route has access to the authenticated user.
3. Confirm existing tests use Saloon fake/mock responses.
4. Add characterization tests for current working behavior.

Add tests for:

```text
redirect returns Zoom authorization URL
authorization URL contains state
authorization URL contains code_challenge
authorization URL contains code_challenge_method=S256
code verifier length is valid
code challenge matches Base64URL(SHA256(verifier))
valid callback sends code_verifier to token endpoint
successful callback saves access/refresh tokens
```

Do not change architecture in this phase.

---

# Phase 2: Store One Authorization Record

Replace separate cache entries:

```text
oauth:zoom:state:{state} → state
oauth:zoom:verifier:{state} → code_verifier
```

with one record:

```php
[
    'user_id' => $user->getKey(),
    'code_verifier' => $redirectDetails->codeVerifier,
]
```

stored under:

```text
oauth:zoom:authorization:{state}
```

Update `ZoomAuthorizationStateStore` to add:

```php
public function store(
    AuthorizationRedirectDetails $redirectDetails,
    User $user,
): void
```

Keep:

- 10-minute TTL
- callback lock
- single-use consumption

Update controller redirect:

```php
$this->authorizationStateStore->store(
    redirectDetails: $redirectDetails,
    user: $this->authenticatedUser(),
);
```

---

# Phase 3: Add Atomic `consume()`

Replace:

```php
validateState($state);
clearState($state);
takeVerifier($state);
```

with:

```php
public function consume(string $state, User $user): string
```

It should:

```text
acquire callback lock
atomically pull authorization record
reject missing/expired/reused state
verify stored user_id matches current user
verify code_verifier exists
return code_verifier
```

Suggested shape:

```php
public function consume(string $state, User $user): string
{
    $authorization = Cache::lock(
        $this->callbackLockKey($state),
        self::CALLBACK_LOCK_SECONDS,
    )->get(
        fn (): mixed => Cache::pull(
            $this->authorizationCacheKey($state)
        ),
    );

    if (! is_array($authorization)) {
        $this->invalidState();
    }

    if (($authorization['user_id'] ?? null) !== $user->getKey()) {
        $this->invalidState();
    }

    $codeVerifier = $authorization['code_verifier'] ?? null;

    if (! is_string($codeVerifier) || $codeVerifier === '') {
        $this->invalidState();
    }

    return $codeVerifier;
}
```

Remove:

- `validateState()`
- `takeVerifier()`
- `clearState()`
- state cache prefix
- verifier cache prefix
- redundant state mismatch message/comparison

Keep a `forget(string $state)` method for OAuth error cleanup.

---

# Phase 4: Simplify `ZoomAuthController`

## Redirect

```php
public function redirect(): JsonResponse
{
    $redirectDetails = $this->zoomOAuth->getAuthRedirectDetails();

    $this->authorizationStateStore->store(
        redirectDetails: $redirectDetails,
        user: $this->authenticatedUser(),
    );

    return $this->respondWithData([
        'redirect_url' => $redirectDetails->authorizationUrl,
    ]);
}
```

## Callback

Use:

```php
$codeVerifier = $this->authorizationStateStore->consume(
    state: $state,
    user: $this->authenticatedUser(),
);
```

Then construct `AuthorizationCallbackDetails`.

---

# Phase 5: Add Callback FormRequest

Create:

```text
app/Http/Requests/Api/V1/Zoom/ZoomAuthorizationCallbackRequest.php
```

Recommended rules:

```php
[
    'code' => ['required_without:error', 'string'],
    'state' => ['required', 'string'],
    'error' => ['sometimes', 'string'],
]
```

Use it in the callback controller method.

---

# Phase 6: Improve OAuth Error Handling

Handle any OAuth error safely:

```php
$error = trim((string) $request->string('error'));

if ($error !== '') {
    $state = trim((string) $request->string('state'));

    if ($state !== '') {
        $this->authorizationStateStore->forget($state);
    }

    abort(
        Response::HTTP_BAD_REQUEST,
        $error === 'access_denied'
            ? 'Zoom account connection denied.'
            : 'Zoom authorization failed.',
    );
}
```

Do not expose raw `error_description`.

Do not log:

- code_verifier
- authorization code
- access token
- refresh token
- client secret

---

# Phase 7: Clean Up `ZoomOAuthService`

Change nullable dependency:

```php
protected readonly ?ZoomConnectorManager $connectors
```

to:

```php
private readonly ZoomConnectorManager $connectors
```

Remove unused imports.

Review:

```php
state: $callbackDetails->state,
expectedState: $callbackDetails->state,
```

This comparison is meaningless because both values are identical.

Rules:

- If Saloon allows omitting `expectedState`, omit it.
- If required by method signature, document that `consume()` is the authoritative state validation.
- Do not remove server-side state validation.

---

# Phase 8: Verify PKCE Token Exchange

Confirm initial token exchange includes:

```php
[
    'grant_type' => 'authorization_code',
    'code' => $authorizationCode,
    'redirect_uri' => $redirectUri,
    'code_verifier' => $codeVerifier,
]
```

Keep refresh flow unchanged:

```php
[
    'grant_type' => 'refresh_token',
    'refresh_token' => $refreshToken,
]
```

Do not send `code_verifier` during refresh.

---

# Phase 9: Security and Failure-Path Tests

Add tests for:

## Redirect

```text
stores authorization record with user ID
stores PKCE verifier
record expires after TTL
authorization URL contains state and S256 challenge
```

## Callback success

```text
valid state for same user succeeds
stored verifier is sent to token endpoint
authorization record is consumed once
tokens are persisted
```

## Invalid state

```text
unknown state rejected
expired state rejected
reused state rejected
missing verifier rejected
```

## User binding

```text
state created by user A cannot be consumed by user B
user mismatch does not save tokens
```

## Concurrency

```text
two callback requests cannot both consume same state
only one token exchange is attempted
```

## OAuth provider errors

```text
access_denied returns controlled message
other OAuth error returns generic message
cached authorization record is forgotten
raw error_description is not exposed
```

## Token exchange failure

```text
invalid_grant does not save tokens
PKCE failure does not save tokens
tokens/verifier are not logged
```

Use Saloon fakes. Do not hit real Zoom endpoints.

---

# Phase 10: Final Cleanup

After tests pass:

```text
remove obsolete methods/constants
remove unused imports
run targeted OAuth tests
run full test suite
run Pint
run Larastan/PHPStan if configured
verify no sensitive values are logged
verify callback route authentication
```

---

# Expected Final Flow

```text
User starts Zoom connection
→ generate random state + PKCE verifier
→ store {user_id, code_verifier} under state key with TTL
→ redirect to Zoom with state + code_challenge

Zoom callback
→ validate request
→ atomically consume state record
→ verify current user owns flow
→ retrieve original verifier
→ exchange code + verifier for tokens
→ save tokens
→ record cannot be reused
```

---

# Anti-Overengineering Rules

Do not:

```text
rewrite ZoomConnector
rewrite meeting APIs
rewrite webhook integration
introduce a generic OAuth framework
move cache storage to DB unless needed
create many tiny services
```

Focus on:

```text
user binding
single authorization record
atomic consume
safe OAuth error handling
meaningful tests
```

---

# Suggested Prompt for Windsurf SWE 1.6

```text
Read PLAN.md first.

I have a Laravel + Saloon Zoom OAuth flow with PKCE already implemented. The flow works, but state/verifier storage needs production hardening.

Implement the plan in phases. Do not rewrite unrelated Zoom meeting, webhook, connector, rate-limit, or refresh-token logic.

Phase order:
1. characterization tests,
2. single authorization record,
3. user binding,
4. atomic consume(),
5. controller simplification,
6. callback FormRequest,
7. OAuth error cleanup,
8. ZoomOAuthService cleanup,
9. security/concurrency/failure tests,
10. final cleanup.

Before each phase:
- summarize the exact change,
- list files to modify,
- keep patches small,
- run relevant tests.

Important requirements:
- state record contains user_id + code_verifier,
- state is short-lived and single-use,
- callback verifies the same authenticated user,
- code_verifier is sent only during authorization-code exchange,
- refresh flow remains unchanged,
- no secrets/tokens/verifier are exposed in logs or API responses.
```
