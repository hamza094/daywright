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
- `Project`, `Task`, and `User` still expose `path()` as compatibility wrappers for legacy callers, but those wrappers remain V1-specific and are now explicitly tracked as remaining cleanup in Phase 6.
- Endpoint URLs remain unchanged in this phase; the goal here was to remove the version leak from resource output first while keeping existing callers stable.

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

**Phase 5 Status**

- Implemented for the current test suite surface.
- Test cases now build V1 and V1 admin endpoints through shared route helpers instead of raw `/api/v1/...` strings.
- The remaining mixed test directories were left in place for incremental cleanup, but the HTTP contract assertions now resolve through named routes so future v2 coexistence is no longer blocked by copied path literals.
- Notification and invited-user payload coverage now generate user self links from route-bound UUIDs, which keeps the contract stable even when the resource is backed by serialized notification data instead of a hydrated `User` model.

**Phase 6 Status**

- Implemented.
- Shared actions, services, jobs, and V1 controller payloads no longer depend on `Project::path()`, `Task::path()`, or `User::path()` to move links around the application layer.
- Notification, webhook, and assignment flows now pass route keys such as project slugs and resolve the final V1 URL only at the last edge that still needs it.
- The model `path()` wrappers remain as compatibility shims for legacy callers and tests, but the shared application layer no longer depends on them for runtime behavior.

**Phase 7 Status**

- Implemented.
- Verification URLs plus notification, mail-action, and webhook-facing project links now flow through a centralized `App\Notifications\NotificationLink` builder.
- Shared notifications and the verification callback no longer hard-code `api.v1.*` route names directly; they delegate the versioned route-name decision to the shared builder.
- The builder still defaults to V1 today, but the version-aware decision point is now centralized so future V2 out-of-band links can be introduced without reopening shared domain flows.

**Phase 8 Status**

- Planned.
- Routes are already version-based, but version ownership is still split across `RouteServiceProvider`, `routes/auth.php`, and `routes/web.php` in slightly different ways.
- The goal is to make V2 registration additive by standardizing how API, admin, auth, session, OAuth, and two-factor route groups are mounted per version.
- When this phase is complete, adding `api.v2.*` should mean wiring a new route file set, not rewriting the existing route plumbing.

**Phase 9 Status**

- Planned.
- The final readiness step is to prove coexistence with a thin first V2 slice rather than a big-bang duplication.
- Duplicate only the contracts that actually change, keep unchanged domains on V1, and add coexistence coverage that proves `api.v1.*` and `api.v2.*` can ship side by side.
- When this phase is complete, the project will have a repeatable pattern for introducing V2 endpoints without regressing V1 consumers.

**Steps**

1. Phase 1. Freeze the versioning contract. Define a single naming rule for future coexistence: api.v1._ and api.v2._. Decide up front that models will no longer own versioned API URLs, and keep the current split where stateless API routes live under the API stack while session, OAuth, and 2FA flows can stay under the web stack if they need session state. Completed for the current v1 route-name surface.
2. Phase 2. Remove version leaks from links and resource output. Start with Project.php, Task.php, and User.php, which currently return hard-coded v1 URLs. Move link generation into versioned resources or a small API link builder. Clean up resource-level auth coupling in ProjectResource.php and replace the hard-coded self link in InvitedUserResource.php.
3. Phase 3. Extract reusable application logic out of App\Services\Api\V1. The first slice should be auth because the asymmetry is already visible in SpaAuthController.php, LoginController.php, and TwoFactorController.php, all of which currently depend on V1 request or service classes. Use LoginUserService.php as the first shared-service extraction target, then repeat the pattern for project, task, subscription, and dashboard logic. Service namespaces should match their actual folders under App\Services rather than preserving an Api\V1 namespace inside the application layer.
4. Phase 4. Thin controllers into versioned HTTP adapters. Refactor controllers that still perform direct mutation work so they only authorize, validate, call a shared use-case, and return a versioned response. Priority examples are ProjectController.php, StageController.php, and UserController.php. Before copying anything for v2, review stale controller paths like InvitationController.php, since active invitation routing already points to ProjectInvitationController.php.
5. Phase 5. Realign the test suite around versioned contracts. Replace hard-coded /api/v1 strings with route names or small helper constants so v1 and later version is maintainable. Gradually normalize test structure by API version plus domain instead of the current mixed layout across Api/V1, Api/Auth, Api/Controllers, and Services. Completed for route-contract coverage; directory normalization can continue incrementally without reopening the versioning contract work.
6. Phase 6. Remove the remaining model-owned V1 URL leakage from shared application code. Replace `->path()` usage in actions, jobs, services, notifications, and controller payloads with route keys or small immutable link payload objects. Keep compatibility wrappers only until all shared-layer consumers are removed. Completed for the current shared-layer callers.
7. Phase 7. Make verification, notification, mail, and webhook links version-aware. Replace direct V1 route references or prebuilt V1 paths with a contract-aware link factory so out-of-band flows can emit V1 or V2 URLs without forking shared domain code. Completed for the current verification and notification/webhook flows.
8. Phase 8. Normalize route registration for additive multi-version support. Keep routes version-specific at the HTTP edge, but standardize how API, admin, auth, session, OAuth, and two-factor route groups are mounted so `api.v2.*` can be added with new files and groups instead of cross-cutting rewrites.
9. Phase 9. Prove coexistence with one thin V2 slice. Copy only the contracts that actually change, add targeted coexistence tests for that domain, and keep unchanged surfaces on V1 until requirements justify duplication.

**Relevant Files**

- app/Providers/RouteServiceProvider.php
- routes/api.php
- routes/auth.php
- routes/web.php
- routes/api/v1.php
- routes/api/admin/v1.php
- app/Models/Project.php
- app/Models/Task.php
- app/Models/User.php
- app/Providers/AuthServiceProvider.php
- app/Http/Resources/Api/V1/Project/ProjectResource.php
- app/Http/Resources/Api/V1/User/InvitedUserResource.php
- app/Http/Controllers/Api/V1/User/AvatarController.php
- app/Actions/Project/SendProjectInvitationAction.php
- app/Actions/Project/AcceptProjectInvitationAction.php
- app/Actions/Project/SendProjectUpdatedNotificationAction.php
- app/Actions/TaskDueAction.php
- app/Services/Project/ConversationService.php
- app/Services/Task/TaskService.php
- app/Jobs/Webhooks/Zoom/StartMeetingWebhook.php
- app/Jobs/Webhooks/Zoom/MeetingEndsWebhook.php
- app/Notifications/NotificationLink.php
- app/Notifications/ProjectInvitation.php
- app/Notifications/AcceptInvitation.php
- app/Notifications/ProjectUpdated.php
- app/Notifications/TaskDue.php
- tests/TestCase.php
- tests/Feature/Api/Auth/AuthenticationTest.php
- tests/Feature/Api/V1/ProjectFeatureTest.php
- tests/Feature/Api/V1/Admin/UsersTest.php
- tests/Feature/Api/V1/UserNotificationsTest.php
- tests/Feature/Api/V1/ProjectInsightsApiTest.php
- tests/Unit/ProjectTest.php

**Verification**

1. After Phase 1, confirm the versioning rules are fixed in writing and that planned v2 route names will not collide with v1.
2. During Phase 2, rerun the link-sensitive tests first, especially ProjectTest.php and the affected project or user feature tests.
3. During Phase 3, validate each extracted shared-service slice with the narrowest auth or domain tests before moving on.
4. During Phase 4, run only the controller-domain tests for the controller you just thinned before touching the next domain.
5. During Phase 5, replace raw v1 URLs in tests in batches and rerun only the touched files each time.
6. During Phase 6, search `app/**` for `->path()` consumers and confirm shared-layer callers no longer depend on versioned URLs coming from models.
7. During Phase 7, validate verification links, notification payloads, mail actions, and webhook payload links with focused tests or assertions for the affected slice.
8. During Phase 8, confirm `api.v1.*` remains unchanged while a new version can be mounted additively without editing unrelated route groups.
9. During Phase 9, run coexistence tests for the first V2 slice together with targeted V1 regression tests for the same domain.

**Decisions**

- Included: route organization, route naming, version boundaries, controller-service responsibilities, API links, and test changes directly needed for a clean v2 transition.
- Excluded: business-rule redesign, unnecessary abstractions, Laravel structure migration, and broad frontend work unrelated to the API boundary.
- Recommended starting slice: auth first, because it exposes the current structural issue most clearly.
- Recommended service strategy: extract shared logic into versionless services before introducing any V2 service namespace.
- Route URLs and route names should stay version-specific at the HTTP edge; shared services, actions, repositories, jobs, and models should not own versioned URLs.
- Recommended next slice before concluding readiness: Phase 8, because route registration is the next remaining cross-cutting version boundary that still needs normalization for additive V2 mounting.
