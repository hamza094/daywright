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

## Phase 2: High Severity IDORs (Tenant Isolation)

**Objective**: Ensure that a token with a valid scope (`projects:read` or `team:read`) cannot access data outside the user's actual project/team (Insecure Direct Object Reference).

### [MODIFY] `app/Http/Controllers/Api/V1/Project/ProjectInsightsController.php`

- Add `$this->authorize('access', $project);` at the top of the `index()` method. The route has `tokenAbility:projects:read`, but we must verify the user actually belongs to the requested project.

### [MODIFY] `app/Http/Controllers/Api/V1/User/UserController.php` & `app/Services/User/UserService.php`

### [MODIFY] `routes/api/v1/users.php`

- Update the `apiResource('/users')` definition to `except(['index', 'store'])`.
- Global user listing is a massive data leak risk (email scraping). Since the Admin panel already has `/admin/users`, the public API should never return a global list of users.
- Third-party developers and the SPA must rely on scoped endpoints (like your existing `GET /projects/{project}/users/search`) to find specific users for invitations.

---

## Phase 3: Token Lifecycle & Session Revocation

**Objective**: Ensure that security-sensitive events (like password resets) properly invalidate existing sessions and API tokens.

### [MODIFY] `app/Http/Controllers/Api/Auth/ResetPasswordController.php`

- Inside the reset logic, explicitly revoke all existing Personal Access Tokens for the user (`$user->tokens()->delete();`) after a successful password reset.

### [MODIFY] `app/Services/User/UserService.php`

- Inside `updateUser()`, if the user is changing their password, revoke all their other existing PATs.

### [MODIFY] `config/sanctum.php`

- Change the global token expiration from `null` to a hard limit (e.g., `43200` minutes / 30 days) to prevent permanent, non-expiring wildcard tokens.

---

## Phase 4: Scope Middleware & CORS Hardening

**Objective**: Fix the logical bypass in the scope middleware and secure the CORS configuration for the SPA.

### [MODIFY] `app/Http/Middleware/CheckTokenAbilities.php`

- The current logic uses `if ($request->bearerToken())`. This can be bypassed if an attacker sends a valid session cookie AND a fake Bearer header.
- Change the check to: `if ($request->user() && $request->user()->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken)`.

### [MODIFY] `config/cors.php`

- If `supports_credentials` is set to `true`, modern browsers will block CORS if `allowed_origins` contains `['*']`. We will update this to strictly list your known frontend domains (e.g., `['http://localhost:3000', 'https://app.daywright.com']`).

### [MODIFY] `app/Http/Requests/Api/V1/User/UserTokenRequest.php`

- Remove the `['*']` wildcard option from the `Rule::in()` validation array. This guarantees that API keys generated from the dashboard are strictly scoped and forces developers to adhere to the Principle of Least Privilege.

---

## Verification Plan

### Automated Tests

- Write a Feature test proving a Bearer token gets a `403` on `POST /api/v1/twofactor/recovery-codes`.
- Write a Feature test proving `GET /api/v1/projects/{project}/insights` returns a `403` if the user is not a member of the project, even if their token has `projects:read`.
- Write a Feature test proving a password reset successfully deletes rows from the `personal_access_tokens` table.
