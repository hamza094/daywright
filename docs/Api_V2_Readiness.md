## Plan: API V2 Readiness

Prepare the codebase for a clean v2 rollout by keeping versioning at the HTTP contract layer only. The core move is: controllers, requests, resources, and route files stay versioned; shared services, actions, repositories, and models become versionless. That gives you selective v2 duplication only where the API contract actually changes.

**Phase 1 Status**

- Implemented.
- The v1 route-name contract is now frozen under `api.v1.*`.
- Web-backed auth routes use `api.v1.session.*`, `api.v1.oauth.*`, and `api.v1.twofactor.*`.
- API-backed named routes use `api.v1.*`, and admin named routes are reserved under `api.v1.admin.*` when named.
- Endpoint URLs remain unchanged in this phase; only the route-name contract was normalized.

**Phase 2 Status**

- Implemented.
- V1 resource link generation now goes through a dedicated V1 link helper instead of hard-coded strings or direct model-owned URL assembly inside resources.
- `ProjectResource`, project collection/summary/stage/invitation resources, task resources, invited user resource, admin project resource, conversation resource, and meeting resource now generate links through the V1 helper.
- `InvitedUserResource` now checks the current Phase 1 route name `api.v1.project.pending.invitation` instead of the old pre-Phase-1 route name.
- `Project`, `Task`, and `User` still expose `path()` as compatibility wrappers, but they no longer hard-code `/api/v1/...` strings directly.
- Endpoint URLs remain unchanged in this phase; the goal was to remove the version leak from resource output while keeping existing callers stable.

**Phase 3 Status**

- Auth slice implemented.
- Shared auth application logic now lives in `App\Services\Auth\LoginUserService` and `App\Services\Auth\RegisterUserService`.
- Legacy `App\Services\Api\V1` service namespaces have been removed from the business-logic service layer; service namespaces now match their real folders under `App\Services\...`.
- Controllers, actions, providers, jobs, and tests now import the folder-based service namespaces directly.
- The dashboard slice is now fully folder-aligned under `App\Services\Dashboard\...` instead of leaving `DashboardService` at the root of `App\Services`.
- The remaining work in this phase is controller thinning and any still-mixed domain logic that is not just a namespace relocation.

**Phase 4 Status**

- Implemented for the current V1 controller surface.
- `ProjectController`, `Project\StageController`, and `User\UserController` now act as HTTP adapters and delegate list/update/delete/restore behavior to shared services.
- The remaining obvious direct-work controllers in V1, including `TokenController`, `Admin\StageController`, and `Admin\StatusController`, now also delegate to shared services instead of mutating models directly in controller methods.
- The stale unused `Project\InvitationController` has been removed because active invitation routing already uses `ProjectInvitationController`, `AcceptProjectInvitationController`, `RejectProjectInvitationController`, and `InvitationUserSearchController`.

**Steps**

1. Phase 1. Freeze the versioning contract. Define a single naming rule for future coexistence: api.v1._ and api.v2._. Decide up front that models will no longer own versioned API URLs, and keep the current split where stateless API routes live under the API stack while session, OAuth, and 2FA flows can stay under the web stack if they need session state. Completed for the current v1 route-name surface.
2. Phase 2. Remove version leaks from links and resource output. Start with Project.php, Task.php, and User.php, which currently return hard-coded v1 URLs. Move link generation into versioned resources or a small API link builder. Clean up resource-level auth coupling in ProjectResource.php and replace the hard-coded self link in InvitedUserResource.php.
3. Phase 3. Extract reusable application logic out of App\Services\Api\V1. The first slice should be auth because the asymmetry is already visible in SpaAuthController.php, LoginController.php, and TwoFactorController.php, all of which currently depend on V1 request or service classes. Use LoginUserService.php as the first shared-service extraction target, then repeat the pattern for project, task, subscription, and dashboard logic. Service namespaces should match their actual folders under App\Services rather than preserving an Api\V1 namespace inside the application layer.
4. Phase 4. Thin controllers into versioned HTTP adapters. Refactor controllers that still perform direct mutation work so they only authorize, validate, call a shared use-case, and return a versioned response. Priority examples are ProjectController.php, StageController.php, and UserController.php. Before copying anything for v2, review stale controller paths like InvitationController.php, since active invitation routing already points to ProjectInvitationController.php.
5. Phase 5. Reorganize routes by resource, not by controller count. Keep one top-level API entry file, but split the current large v1.php into a small number of resource-oriented files only where that improves navigation. A practical target is dashboard, projects, users, integrations, and admin. Keep versioned session, OAuth, and 2FA endpoints grouped consistently between auth.php and web.php. Centralize JSON fallback behavior through api.php and keep route mounting explicit in RouteServiceProvider.php.
6. Phase 6. Add the v2 HTTP surface selectively. Only create V2 controllers, requests, and resources for endpoints whose request or response contract actually changes. Do not create a full mirror of App\Services\Api\V2 by default. Reuse the shared services and actions extracted in Phase 3, and let v1 and v2 differ only at the transport layer where necessary.
7. Phase 7. Realign the test suite around versioned contracts. Replace hard-coded /api/v1 strings with route names or small helper constants so v1 and v2 can be tested side by side. Start with AuthenticationTest.php, ProjectFeatureTest.php, UsersTest.php, and ProjectTest.php. Gradually normalize test structure by API version plus domain instead of the current mixed layout across Api/V1, Api/Auth, Api/Controllers, and Api/Services.
8. Phase 8. Introduce v2 incrementally. Register v2 beside v1 only after route-name collisions, hard-coded links, and service-boundary issues are resolved. Migrate one domain at a time, keep v1 stable, and defer retirement until v2 contract coverage and client adoption are complete.

**Relevant Files**

- RouteServiceProvider.php
- api.php
- auth.php
- web.php
- v1.php
- v1.php
- Project.php
- Task.php
- User.php
- ProjectResource.php
- InvitedUserResource.php
- SpaAuthController.php
- LoginController.php
- OAuthController.php
- TwoFactorController.php
- LoginUserService.php
- LoginUserRequest.php
- ProjectService.php
- TaskService.php
- DashboardService.php
- ProjectController.php
- StageController.php
- UserController.php
- AuthenticationTest.php
- ProjectFeatureTest.php
- UsersTest.php
- ProjectTest.php

**Verification**

1. After Phase 1, confirm the versioning rules are fixed in writing and that planned v2 route names will not collide with v1.
2. During Phase 2, rerun the link-sensitive tests first, especially ProjectTest.php and the affected project or user feature tests.
3. During Phase 3, validate each extracted shared-service slice with the narrowest auth or domain tests before moving on.
4. During Phase 4, run only the controller-domain tests for the controller you just thinned before touching the next domain.
5. During Phase 5, verify the registered routes after each route-file split and rerun the affected feature tests.
6. During Phase 7, replace raw v1 URLs in tests in batches and rerun only the touched files each time.
7. Before enabling any v2 routes for real clients, run the focused v1 and v2 contract tests for the migrated domains, then the broader API suite.

**Decisions**

- Included: route organization, route naming, version boundaries, controller-service responsibilities, API links, and test changes directly needed for a clean v2 transition.
- Excluded: business-rule redesign, unnecessary abstractions, Laravel structure migration, and broad frontend work unrelated to the API boundary.
- Recommended starting slice: auth first, because it exposes the current structural issue most clearly.
- Recommended service strategy: extract shared logic into versionless services before introducing any V2 service namespace.
