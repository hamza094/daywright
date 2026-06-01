# Non-Admin Backend Consistency Plan

Created: 2026-05-14

This plan standardizes controller, service, action, and repository boundaries across the non-admin backend.
It is intentionally conservative: clean up drift, remove duplicate surfaces, and stop new inconsistency without broad rewrites before production.

Work through the phases in order.
Do not pull the separate query-level pagination and filtering work into this plan.

## Implementation Status

- Phases 1, 2, 3, 5, 6, and 7 are implemented.
- Phase 4 is implemented except for `ProjectRepository`, which remains intentionally deferred until the separate query-level pagination and filtering PR settles its final role.
- The final Phase 7 sweep removed merged and dead surfaces, confirmed the non-admin route and dependency rules, and cleaned up one stale leftover dependency in `DashboardService`.

## Scope

In scope:

- Non-admin controllers under `app/Http/Controllers/Api` and `app/Http/Controllers/Api/V1`
- Non-admin services under `app/Services`
- Non-admin actions under `app/Actions`
- Non-admin repositories under `app/Repository`
- Related non-admin routes in `routes/api/v1.php`

Out of scope:

- Anything under `Admin`
- The separate PR for query-level pagination and filtering
- Frontend changes
- New abstractions that do not remove an existing inconsistency

## Goal

- Make the non-admin backend easier to reason about before production.
- Use one clear boundary model for controllers, services, actions, and repositories.
- Keep the refactor pragmatic and avoid architecture churn.

## Target Conventions

### Controllers

- Validate input and authorize access.
- Call one main collaborator for the use case.
- Return a resource, resource collection, or a small JSON payload.
- Resource controllers stay for canonical CRUD.
- Invokable controllers stay for one-off commands and queries.
- Use constructor or method injection. Do not use `app()` inside controllers.

### Services

- Own application use cases and orchestration.
- Accept models, scalars, arrays, or DTOs.
- Do not accept Form Request objects.
- Do not call `auth()`, `request()`, or `Auth::user()` unless the service is explicitly auth or session oriented.
- May coordinate actions, repositories, transactions, notifications, and integrations.

### Actions

- Represent one focused domain operation.
- Default public method name: `execute()`.
- Prefer injected instance actions over static helper-style actions.
- Controllers should call actions directly only for tiny isolated operations. Most action usage should sit under a service.

### Repositories

- Stay query-only.
- Return models, collections, paginators, arrays, or DTOs.
- Do not return API resources.
- Do not accept `Request` objects.
- Do not read `Auth` or `auth()`.
- If a class is not mainly data access, it should not keep a `Repository` name.

### Routes

- Keep one active controller surface per behavior.
- If a smaller single-purpose controller already owns the routed endpoint, retire the older feature-bucket controller.

## Phase 1 - Freeze The Rules

Goal:

- Stop new architectural drift before moving files.

Tasks:

- [ ] Treat the target conventions in this file as the default for all new non-admin backend work.
- [ ] Stop using `app()` for controller dependencies.
- [ ] Use `execute()` for any new non-admin action introduced from this point forward.
- [ ] Stop adding new repositories that depend on `Request`, `Auth`, or API resources.
- [ ] Stop adding new feature-bucket controllers.

Review first:

- `app/Http/Controllers/Api/V1/TokenController.php`
- `app/Http/Controllers/Api/OAuth/ZoomAuthController.php`
- Any newly touched action class that still uses `handle()`, `apply()`, or static helper methods

Exit criteria:

- New work follows one boundary model even before old code is fully cleaned up.

## Phase 2 - Remove Duplicate Controller Surfaces

Goal:

- Make one controller path authoritative for each behavior.

Keep as the canonical routed surfaces:

- `ProjectMessageController`
- `ScheduledProjectMessagesController`
- `AssignTaskMembersController`
- `UnassignTaskMemberController`
- `ArchiveTaskController`
- `RestoreTaskController`
- `TaskMemberSearchController`

Tasks:

- [ ] Retire `TaskFeaturesController` after confirming its behavior is fully covered by the single-purpose task controllers already routed in `routes/api/v1.php`.
- [ ] Retire `MessageController` after confirming `ProjectMessageController` and `ScheduledProjectMessagesController` fully cover message flows.
- [ ] Keep `ProjectController`, `TaskController`, `ZoomMeetingController`, `SubscriptionController`, `NotificationsController`, and `UserController` as the main multi-method controllers.
- [ ] Leave small invokable controllers in place where the route shape is already clear.

Review first:

- `app/Http/Controllers/Api/V1/Task/TaskFeaturesController.php`
- `app/Http/Controllers/Api/V1/Project/MessageController.php`
- `routes/api/v1.php`

Exit criteria:

- No duplicate non-admin controller surface remains for task features or project messaging.
- Routes point to one obvious home for each behavior.

## Phase 3 - Merge Ambiguous Services Into Canonical Application Services

Goal:

- Remove vague or pass-through services that do not add enough boundary value.

Tasks:

- [ ] Merge `TaskFeatureService` into `TaskService`.
- [ ] Merge `FeatureService` into `ProjectService`.
- [ ] Keep `InvitationService` and `MessageService` separate because they represent distinct project workflows.
- [ ] Keep `SubscriptionViewService` and `PlanLimitService` separate.
- [ ] Delete merged services only after all callers are updated.

Target result:

- `TaskService` owns task create, update, assign, unassign, archive, restore, and delete.
- `ProjectService` owns project create, update, delete, restore, force-delete, stage updates, and project-owner-only limits.

Review first:

- `app/Services/Task/TaskFeatureService.php`
- `app/Services/Task/TaskService.php`
- `app/Services/Project/FeatureService.php`
- `app/Services/Project/ProjectService.php`

Exit criteria:

- No vague `FeatureService` remains in the non-admin backend.
- No service exists only to forward one-line calls into actions.

## Phase 4 - Normalize Repository Boundaries

Goal:

- Make `Repository` mean query-only everywhere.

Keep as repositories if they stay query-only:

- `DashboardInsightsRepository`
- `UserTasksDataRepository`
- `TaskRepository`
- The non-admin dashboard read repository after rename
- `ProjectRepository` only after the separate query PR settles its real role

Tasks:

- [ ] Rename `DashBoardRepository` to `DashboardRepository` or `UserDashboardRepository`.
- [ ] Remove `Request` and `Auth` coupling from the dashboard read repository.
- [ ] Make `ConversationRepository` return data, not resources. If it keeps shaping API resources, rename it instead of keeping `Repository`.
- [ ] Reassess `ProjectRepository` after the separate query PR. If it remains a collection filter/helper, move or rename it out of `Repository`.
- [ ] Merge `ProjectInsightsRepository` into `ProjectInsightService`, or rename it to match what it actually does if you want to keep the split.

Review first:

- `app/Repository/DashBoardRepository.php`
- `app/Repository/Api/V1/ConversationRepository.php`
- `app/Repository/ProjectRepository.php`
- `app/Repository/ProjectInsightsRepository.php`
- `app/Services/Project/ProjectInsightService.php`

Exit criteria:

- No repository returns `JsonResource` or resource collections.
- No repository accepts `Request`.
- No repository reads auth state.
- Remaining repositories are clearly query and data-access classes.

## Phase 5 - Tighten Service Inputs And Side Effects

Goal:

- Keep services independent from HTTP except where auth or session behavior is the actual job.

Tasks:

- [ ] Refactor `ConversationService` to accept validated payload and actor or project data instead of `ConversationRequest` and `auth()` calls.
- [ ] Refactor `DashboardService` to take a `User` explicitly instead of reading `Auth::user()`.
- [ ] Keep `LoginUserService` allowed to touch `Request` and `Auth` because session login is its actual responsibility.
- [ ] Keep authorization and request validation in controllers or Form Requests.
- [ ] Pass the acting user explicitly into project and task mutation methods when needed.

Review first:

- `app/Services/Project/ConversationService.php`
- `app/Services/Dashboard/DashboardService.php`
- `app/Services/Auth/LoginUserService.php`

Exit criteria:

- Non-auth domain services no longer depend on Form Requests, `request()`, or `auth()`.
- Auth or session services are the only place where HTTP and session coupling remains intentional.

## Phase 6 - Standardize Action Contracts

Goal:

- Make actions easy to recognize and use consistently.

Tasks:

- [ ] Standardize on an instance `execute()` method for non-framework actions.
- [ ] Rename `handle()` methods that are really application actions.
- [ ] Replace static helper-style actions with instance actions when they are part of normal domain flow.
- [ ] Update service and controller callers in the same phase as each rename.

Primary rename candidates:

- `ZoomAction`
- `ProjectHealthRecalculationAction`
- `BulkDeleteProjectsAction`
- `BulkDeleteTasksAction`
- `NotificationAction`

Rule:

- If an action is still a tiny pure helper with no state and no reuse pain, it can wait.
- If an action is already part of the main workflow surface, rename it in this phase.

Exit criteria:

- The main non-admin action layer mostly reads the same: injected action plus `execute()`.

## Phase 7 - Final Cleanup And Leave-As-Is Decisions

Goal:

- Finish the pass without rewriting stable low-risk endpoints.

Leave as-is for now:

- `StageController` and `StageService`
- `UserInvitationsController`
- `ForceDeleteUserController`
- `ExportProjectController`
- `ProjectLimitsController`
- `NotificationsController` with `NotificationService`
- `AvatarController` with `AvatarService`

Reason:

- These are small, understandable, and not worth broad refactors before production.

Final cleanup tasks:

- [x] Remove merged or dead controllers and services.
- [x] Remove unused methods left behind by merges.
- [x] Update tests only for touched controller and service boundaries.
- [x] Do a final route and dependency sweep for `app()`, `Request` in services, `Auth` in repositories, and duplicate controller surfaces.

Exit criteria:

- The non-admin backend has one clear boundary model.
- Old feature-bucket leftovers are gone.
- The codebase is more consistent without adding new abstraction layers.

## Recommended Disposition By File

### Keep

- `app/Http/Controllers/Api/V1/Project/ProjectController.php`
- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Controllers/Api/V1/Project/ZoomMeetingController.php`
- `app/Http/Controllers/Api/V1/SubscriptionController.php`
- `app/Http/Controllers/Api/V1/NotificationsController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectMessageController.php`
- `app/Http/Controllers/Api/V1/Project/ScheduledProjectMessagesController.php`
- `app/Services/Project/InvitationService.php`
- `app/Services/Project/MessageService.php`
- `app/Services/Subscription/PlanLimitService.php`
- `app/Services/Subscription/SubscriptionViewService.php`
- `app/Services/Dashboard/DashboardInsightsService.php`
- `app/Repository/DashboardInsightsRepository.php`
- `app/Repository/UserTasksDataRepository.php`

### Rename

- `app/Repository/DashBoardRepository.php` -> `DashboardRepository.php` or `UserDashboardRepository.php`
- `app/Actions/ZoomAction.php` -> keep the class purpose but align its public contract to `execute()`
- `app/Actions/ProjectMetrics/ProjectHealthRecalculationAction.php` -> keep the class but align its public contract to `execute()`
- `app/Actions/NotificationAction.php` -> either make it an injected instance action with `execute()` or rename it to match a dispatcher role if kept separate
- `app/Repository/Api/V1/ConversationRepository.php` -> rename only if it remains a response builder instead of a query repository

### Merge

- `app/Services/Task/TaskFeatureService.php` -> merge into `app/Services/Task/TaskService.php`
- `app/Services/Project/FeatureService.php` -> merge into `app/Services/Project/ProjectService.php`
- `app/Repository/ProjectInsightsRepository.php` -> prefer merging into `app/Services/Project/ProjectInsightService.php`
- `app/Http/Controllers/Api/V1/Task/TaskFeaturesController.php` -> retire in favor of the single-purpose task controllers
- `app/Http/Controllers/Api/V1/Project/MessageController.php` -> retire in favor of `ProjectMessageController` and `ScheduledProjectMessagesController`

### Leave As-Is

- `app/Http/Controllers/Api/V1/Project/StageController.php`
- `app/Services/Project/StageService.php`
- `app/Http/Controllers/Api/V1/User/UserInvitationsController.php`
- `app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php`
- `app/Http/Controllers/Api/V1/Project/ExportProjectController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectLimitsController.php`
- `app/Http/Controllers/Api/V1/User/AvatarController.php`
- `app/Services/User/AvatarService.php`

### Deferred

- `app/Repository/ProjectRepository.php`

Reason:

- Its final role depends on the separate PR that will move project activity filtering and pagination to the query layer.

## Suggested Execution Order

1. Phase 1
2. Phase 2
3. Phase 3
4. Phase 4, excluding `ProjectRepository`
5. Phase 5
6. Phase 6
7. Revisit `ProjectRepository` after the separate query PR
8. Phase 7 final sweep
