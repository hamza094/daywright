# Daywright API Security Route Remediation Plan

This plan breaks down the semantic route-to-ability audit findings into a safe, phased implementation strategy.

## Proposed Changes

### Phase 1: Secure the Admin Panel

The admin routes currently accept any valid API key due to a lack of scope middleware. We will introduce a strict `BlockApiKeys` middleware to reject all Bearer tokens on the admin API, ensuring only browser-based sessions can access these critical endpoints.

#### [NEW] `app/Http/Middleware/BlockApiKeys.php`

- Create a new middleware class `BlockApiKeys`.
- If `$request->bearerToken()` is truthy, throw an `AccessDeniedHttpException` ("API Keys cannot be used for administrative actions.").

#### [MODIFY] `bootstrap/app.php` (or Kernel.php)

- Register the new middleware alias: `'block.api.keys' => App\Http\Middleware\BlockApiKeys::class`.

#### [MODIFY] `routes/api/admin/v1.php`

- Inject the new `block.api.keys` middleware into the root `Route::group` array alongside `auth:sanctum`, `admin`, etc.
- No other changes to the routes in this file are needed.

---

### Phase 2: Secure Global Routes

Address the missing middleware on root-level API endpoints that expose the application to unauthorized mutations.

#### [MODIFY] `routes/api/v1.php`

- Update the global `Route::apiResource('/projects', ProjectController::class)` to explicitly enforce scopes.
- Chain `->middlewareFor(['index'], 'tokenAbility:projects:read')`.
- Chain `->middlewareFor(['store', 'update', 'destroy'], 'tokenAbility:projects:write')`.

#### [MODIFY] `routes/api/v1/oauth.php`

- Update the `ZoomAuthController` group.
- Add `tokenAbility:account:write` to protect the integration connect flow.

---

### Phase 3: Dashboard Domain Corrections

The dashboard currently requires `account:read` to fetch project, task, and activity data, which breaks domain mapping rules and blocks read-only project keys.

#### [MODIFY] `routes/api/v1/dashboard.php`

- Replace all instances of `tokenAbility:account:read` with `tokenAbility:projects:read`.
- Affected routes: `chart-data`, `insights`, `tasks`, `activities`, `projects`.

---

### Phase 4: Core Project Route Refinements

Fix over-privileged and missing scopes on granular project and task routes.

#### [MODIFY] `routes/api/v1/tasks.php`

- Add `->middleware('tokenAbility:projects:read')` to `GET /stages`.
- Add `->middleware('tokenAbility:projects:read')` to `GET /task-statuses`.

#### [MODIFY] `routes/api/v1/projects/core.php`

- Update `GET /limits` to use `tokenAbility:projects:read` instead of `projects:write`.

---

## Verification Plan

### Automated Tests

- Create or update a test in `tests/Feature/Api/Admin/AdminSecurityTest.php` to verify that an API key receives a 403 when attempting to hit an admin route (e.g. `GET /api/admin/projects`).
- Create or update a test in `tests/Feature/Api/ScopeMiddlewareTest.php` to verify that an API key with `account:read` receives a 403 when hitting `POST /api/v1/projects`.

## Additional Considerations

SPA Session Bypass

Ensure the custom CheckTokenAbilities middleware is still applied after these changes
The plan should verify that SPA sessions (without Bearer tokens) continue to work
Test Coverage Gaps

Add tests for write operations with read-only scopes
Add tests for dashboard access with different scope combinations
Test admin routes with session auth vs token auth

### Manual Verification

- Review the route definitions via `php artisan route:list --path=api/v1` (or Scramble docs if configured) to confirm middleware assignments.
- Test dashboard loading in the UI to ensure no session-based auth flows were broken by the domain change.
