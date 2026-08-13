# DayWright Security Remediation Plan

This plan breaks down the findings from the CodeX Security Audit into four actionable implementation phases, ordered by risk severity.

## Phase 1: Critical 2FA Exposure & Security ✅ COMPLETED

**Objective**: Prevent API tokens from bypassing 2FA management flows and enforce re-authentication before disabling 2FA.

### [MODIFY] `routes/web/v1.php` ✅ COMPLETED

- Apply the `RequireSessionAuth` middleware to the entire `auth:sanctum` group under the `twofactor.` prefix.
- Change `GET recovery-codes` to `POST recovery-codes` since generating new codes is a mutation.

### [MODIFY] `app/Http/Middleware/RequireSessionAuth.php` ✅ COMPLETED

- Update the hardcoded error message in the middleware to be generic (e.g., "This operation is strictly reserved for the web dashboard...") so it applies to 2FA and Admin endpoints.

### [MODIFY] `routes/api/admin/v1.php` ✅ COMPLETED

- Replace `block.api.keys` with `RequireSessionAuth` in the admin route group middleware stack.

### [DELETE] `app/Http/Middleware/BlockApiKeys.php`

- Delete this middleware as it is entirely replaced by the more robust `RequireSessionAuth`. ✅ COMPLETED

### [MODIFY] `routes/api/v1/users.php` ✅ COMPLETED

- Apply the `RequireSessionAuth` middleware to the `users/me/subscription` route group to prevent API keys from altering billing data.

### [MODIFY] `app/Http/Requests/Api/V1/Auth/DisableTwoFactorRequest.php` ✅ COMPLETED

- Add validation rules to require the user's `current_password` and a `code` (TOTP or recovery) to prove identity before disabling 2FA.

### [MODIFY] `app/Http/Controllers/Api/Auth/TwoFactorController.php` ✅ COMPLETED

- Update `disableTwoFactorAuth` to validate the provided password and TOTP code.
- Update `showRecoveryCodes` (or create `generateRecoveryCodes`) to require password validation before returning fresh codes.

---

## Phase 2: High Severity IDORs (Tenant Isolation) ✅ COMPLETED

**Objective**: Ensure that a token with a valid scope (`projects:read` or `team:read`) cannot access data outside the user's actual project/team (Insecure Direct Object Reference).

### [MODIFY] `app/Http/Controllers/Api/V1/Project/ProjectInsightsController.php` ✅ COMPLETED

- Add `$this->authorize('access', $project);` at the top of the `index()` method. The route has `tokenAbility:projects:read`, but we must verify the user actually belongs to the requested project.

### [MODIFY] `app/Http/Controllers/Api/V1/User/UserController.php` ✅ COMPLETED

- Removed `index()` method and `UserIndexRequest` import as global user listing is now disabled.

### [MODIFY] `app/Services\User/UserService.php` ✅ COMPLETED

- Removed `paginateUsers()` method as it's no longer needed.

### [MODIFY] `routes/api/v1/users.php` ✅ COMPLETED

- Update the `apiResource('/users')` definition to `except(['index', 'store'])`.
- Global user listing is a massive data leak risk (email scraping). Since the Admin panel already has `/admin/users`, the public API should never return a global list of users.
- Third-party developers and the SPA must rely on scoped endpoints (like your existing `GET /projects/{project}/users/search`) to find specific users for invitations.

---

## Phase 3: Secure Password Management & Session Revocation ✅ COMPLETED

**Objective**: Secure password changes to first-party clients only, and properly revoke web sessions (not API tokens) on password resets without breaking integrations.

### [NEW] `app/Http/Middleware/RequireFirstPartyAuth.php`

- Create a new middleware that allows Web Sessions (TransientToken) OR official Mobile App tokens ($currentToken->can('\*')).
- Strictly blocks third-party developer API keys from password changes.
- Implementation: Check if `$currentToken` is null (web session) or `$currentToken->can('*')` (mobile wildcard tokens).

### [NEW] `app/Http/Controllers/Api/V1/User/PasswordUpdateController.php`

- Create a dedicated controller for password updates to follow separation of concerns best practices.
- Implement an `update()` method that handles password changes with proper validation.
- Use the existing `UserService@updatePassword()` method and add session invalidation.
- Return appropriate success/error responses.

### [NEW] `app/Http/Requests/Api/V1/User/PasswordUpdateRequest.php`

- Create a dedicated form request for password validation.
- Include validation rules: current_password, password, password_confirmation.
- Use Laravel's built-in `current_password` validation rule to verify current_password matches user's existing password.
- Use Laravel's built-in `confirmed` rule to validate password matches password_confirmation.
- Use Laravel's `Password::default()` for strong password requirements.

### [MODIFY] `routes/api/v1/users.php`

- Add a new route: `PUT /users/me/password` pointing to `PasswordUpdateController@update`.
- Guard this route with the new `RequireFirstPartyAuth` middleware.
- Add rate limiting middleware: `throttle:sensitive-password`.

### [MODIFY] `app/Http/Requests/Api/V1/User/UserRequest.php`

- Remove password validation rules from the general user update request.
- This request should only handle profile data (name, email, avatar, etc.).
- Password changes are now handled exclusively by PasswordUpdateRequest.

### [MODIFY] `app/Services/User/UserService.php`

- Remove password update logic from `updateUser()` - this method should only handle profile data.
- Enhance `updatePassword()` to invalidate other web sessions using `Auth::guard('web')->logoutOtherDevices($password)`.
- Keep the existing `PasswordUpdateEvent` for email notifications.
- CRITICAL: Do NOT automatically delete Personal Access Tokens - API keys are independent credentials.

### [MODIFY] `app/Http/Controllers/Api/Auth/ResetPasswordController.php`

- Inside the reset logic, invalidate other web sessions (e.g., using Laravel's session handling).
- Add `Auth::guard('web')->logoutOtherDevices($newPassword)` to invalidate other browser sessions.
- CRITICAL: Do NOT automatically delete Personal Access Tokens ($user->tokens()->delete()). API keys should only be revoked manually by the user.

### [MODIFY] Frontend Components

- Update password change forms to use the new `PUT /users/me/password` endpoint instead of the general user update endpoint.
- Update error handling to handle the new 403 response if third-party tokens attempt password changes.
- Ensure password change UI continues to work for web sessions and mobile apps.

### [MODIFY] Tests

- Add test to verify third-party API keys cannot change passwords (expect 403).
- Add test to verify web sessions can change passwords successfully.
- Add test to verify mobile wildcard tokens can change passwords successfully.
- Add test to verify other web sessions are invalidated after password change.
- Add test to verify API tokens remain valid after password change (not revoked).
- Update existing user update tests to reflect password logic separation.

---

## Phase 4: CORS Hardening & Token Scope Policy

**Objective**: Secure the CORS configuration for the SPA and implement context-aware wildcard token restrictions.

### [SKIP] `app/Http/Middleware/CheckTokenAbilities.php`

- **Decision**: Do not modify the current logic.
- **Reasoning**: The audit's concern about bypassing with session cookie + fake Bearer header is misguided. The current implementation correctly follows Laravel Sanctum's intended design:
  - Session-based requests rely on Laravel policies (`can:` middleware)
  - Token-based requests use Sanctum abilities (`tokenAbility:` middleware)
  - The `bearerToken()` check is a routing decision, not a security gate
  - A fake Bearer header would still fail token validation
  - Proposed fix would add unnecessary database queries on every request
- **Current Usage**: The middleware is used 56 times across API routes for scopes: `account:*`, `projects:*`, and `team:*`

### [MODIFY] `config/cors.php`

- **Status**: Implement this change ✅
- **Reasoning**: Valid security concern. Modern browsers block CORS when `supports_credentials: true` and `allowed_origins: ['*']` are combined.
- **Implementation**: Update to use specific domains:
  ```php
  'allowed_origins' => [
      env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'),
      'https://app.daywright.com',
      // Add production/staging domains as needed
  ],
  ```
- **Caveat**: Ensure this is environment-aware (localhost for dev, specific domains for prod)

### [MODIFY] `app/Http/Requests/Api/V1/User/UserTokenRequest.php`

- **Status**: Implement ✅
- **Reasoning**:
  - Mobile apps use `/auth/login` endpoint which creates wildcard tokens automatically via `LoginUserService@createApiToken($user, $name, ['*'])`
  - UserTokenRequest is only used for manual API key creation via dashboard (TokenController@store)
  - Removing wildcard from UserTokenRequest prevents third-party developers from creating overly-permissive tokens
  - Mobile apps are unaffected since they don't use this form request
- **Implementation**: Remove `['*']` from validation:
  ```php
  'scopes.*' => [
      'required',
      'string',
      Rule::in(ApiScope::values()), // Remove ['*']
  ],
  ```
- **Production Best Practice**:
  - Official apps (mobile) use login endpoint with hardcoded wildcard tokens
  - Third-party API keys are restricted to specific scopes (least privilege)
  - Mobile tokens are short-lived (30 days) and deleted on logout

---

## Verification Plan

### Automated Tests

- Write a Feature test proving a Bearer token gets a `403` on `POST /api/v1/twofactor/recovery-codes`.
- Write a Feature test proving `GET /api/v1/projects/{project}/insights` returns a `403` if the user is not a member of the project, even if their token has `projects:read`.
- Write a Feature test proving a password reset successfully deletes rows from the `personal_access_tokens` table.
