# API Controller And Service Refactor Plan

Created: 2026-05-01

This plan is for cleaning up controller boundaries, route conventions, and action/service usage without overengineering the app.

## Goal

- Keep resource controllers where they already fit.
- Split only the controllers that are clearly mixing multiple concerns.
- Add action classes only for command-style workflows that are already too broad inside services.
- Avoid rewriting the whole API at once.

## Keep These As They Are

These already fit the app reasonably well and should stay as multi-method resource controllers for now:

- `app/Http/Controllers/Api/V1/Project/ProjectController.php`
- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Controllers/Api/V1/Project/ZoomMeetingController.php`
- `app/Http/Controllers/Api/V1/TokenController.php`
- `app/Services/Api/V1/Task/TaskService.php`
- `app/Services/Api/V1/NotificationService.php`

These already fit well as single-action controllers:

- `app/Http/Controllers/Api/V1/Project/ProjectLimitsController.php`
- `app/Http/Controllers/Api/V1/Task/TaskStatusController.php`

## Main Problems To Fix

- Some controllers are acting like feature buckets instead of resource controllers.
- Some routes use command/query endpoints that do not fit the current controller names well.
- Some service classes are really holding multiple command handlers and should be split into actions.
- A few routes and response patterns do not follow REST conventions cleanly.

## Phase 1 - Fix Clear Route And REST Mismatches

Goal:

- Fix the obvious mismatches first without moving a lot of files.

Tasks:

- Move admin backup out of `GET`.
- Stop returning `204` responses with JSON bodies.
- Normalize method names that are too custom when a simple REST verb already exists.

Files to review first:

- `routes/api/v1.php`
- `routes/api/admin/v1.php`
- `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- `app/Http/Controllers/Api/V1/Project/MessageController.php`
- `app/Http/Controllers/Api/V1/Project/ConversationController.php`
- `app/Http/Controllers/Api/V1/User/AvatarController.php`

Concrete changes:

- Change admin backup route from `GET /admin/backup/database` to `POST`.
- Use `204` only for true no-content responses.
- Prefer `destroy` over `delete` for controller method naming.
- Prefer `store` and `destroy` over `avatar` and `removeAvatar` if avatar stays in one controller.

Exit criteria:

- No route/controller parameter mismatches remain.
- No `GET` endpoints trigger server-side mutation work.
- No `204` responses include a body.

## Phase 2 - Split The Most Overloaded Controllers

Goal:

- Split only the controllers that clearly combine unrelated commands and queries.

Controllers to split first:

- `app/Http/Controllers/Api/V1/ProjectDashboardController.php`
- `app/Http/Controllers/Api/V1/Project/InvitationController.php`
- `app/Http/Controllers/Api/V1/Task/TaskFeaturesController.php`
- `app/Http/Controllers/Api/V1/Project/MessageController.php`

Recommended target shape:

### Dashboard

Replace one large dashboard controller with small query controllers:

- `DashboardProjectsController`
- `DashboardTasksController`
- `DashboardActivitiesController`
- `DashboardChartDataController`
- `DashboardKpisController`

### Invitations

Split project invitation responsibilities:

- `ProjectInvitationController`
  - `index`
  - `store`
  - `destroy`
- `AcceptProjectInvitationController`
- `RejectProjectInvitationController`
- `ProjectMemberController`
  - remove member
- `UserSearchController` or `InvitationUserSearchController`

### Task features

Split task command endpoints:

- `AssignTaskMembersController`
- `UnassignTaskMemberController`
- `ArchiveTaskController`
- `RestoreTaskController`
- `DestroyTaskController`
- `TaskMemberSearchController`

### Messages

Split project messaging into one resource-like controller and one query controller:

- `ProjectMessageController`
  - `store`
  - `destroy`
- `ScheduledProjectMessagesController`
  - `index`

Exit criteria:

- Each split controller has one small concern.
- Routes remain close to the current API shape.
- No broad feature bucket controllers remain in the high-traffic paths.

## Phase 3 - Add High-Value Action Classes

Goal:

- Extract only the command workflows that are already acting like standalone units.

Add actions for these first:

### Invitation actions

Current service:

- `app/Services/Api/V1/InvitationService.php`

Recommended actions:

- `SendProjectInvitationAction`
- `AcceptProjectInvitationAction`
- `RejectProjectInvitationAction`
- `CancelProjectInvitationAction`
- `RemoveProjectMemberAction`

Reason:

- The service is currently holding several separate commands.

### Task feature actions

Current service:

- `app/Services/Api/V1/Task/TaskFeatureService.php`

Recommended actions:

- `AssignTaskMembersAction`
- `UnassignTaskMemberAction`
- `ArchiveTaskAction`
- `RestoreTaskAction`
- `DeleteTaskAction`

Reason:

- These methods are atomic commands and map well to controller actions.

### Project actions

Current service:

- `app/Services/Api/V1/ProjectService.php`

Recommended actions:

- `ForceDeleteAbandonedProjectAction`
- `SendProjectUpdatedNotificationAction`

Reason:

- `forceDeleteIfAbandoned()` is already a clear command.
- It should not manually instantiate another action inside the service.

### Message actions

Current service:

- `app/Services/Api/V1/MessageService.php`

Recommended actions:

- `CreateProjectMessageAction`
- `DispatchProjectMessageAction`
- `ScheduleProjectMessageAction`

Reason:

- Message sending now handles validation, persistence, dispatch, and scheduling in one class.

What not to split yet:

- `DashboardService`
- `NotificationService`
- `TaskService`

Exit criteria:

- Command-heavy services are reduced.
- Controllers call one service or one action, not a large mixed workflow.
- No manual `new SomeAction()` calls remain in services.

## Phase 4 - Clean Up Service Boundaries

Goal:

- Make services more consistent after controller and action extraction.

Tasks:

- Stop using `auth()` and `Auth::user()` deep inside services where practical.
- Pass the acting user explicitly into command methods.
- Keep query services focused on fetching and shaping data.
- Keep action classes focused on one mutation workflow.
- Keep controllers thin and orchestration-only.

Files to review:

- `app/Services/Api/V1/InvitationService.php`
- `app/Services/Api/V1/ProjectService.php`
- `app/Services/Api/V1/Task/TaskFeatureService.php`
- `app/Services/Api/V1/MessageService.php`
- `app/Services/Api/V1/DashboardService.php`

Exit criteria:

- Services no longer depend heavily on global auth state.
- Mutation logic is mostly in actions or tightly-scoped services.
- Query logic stays in repositories or query-oriented services.

## Phase 5 - Test And Stabilize

Goal:

- Lock the refactor down with focused tests, not broad rewrites.

Tests to add or update:

- Invitation accept/reject/cancel/remove flows
- Task assign/unassign/archive/restore/remove flows
- Scheduled message list/create/delete flows
- Admin backup route method change
- Dashboard query endpoints after controller split

Run at minimum:

- Focused feature tests for touched controllers
- Focused tests for invitation, task feature, and message flows
- `vendor/bin/pint --dirty`

Exit criteria:

- New controller boundaries are covered by focused tests.
- Route changes are validated.
- No behavior change slips in during the split.

## Simple Working Rules

Use these rules during the refactor:

- Keep CRUD resources in one controller.
- Split command endpoints into small controllers only when the controller is already overloaded.
- Add action classes only for mutation workflows, not for simple reads.
- Do not create actions for everything.
- Do not split stable controllers just for style.

## Recommended Order

1. Phase 1 first.
2. Split `InvitationController`.
3. Split `TaskFeaturesController`.
4. Split `MessageController`.
5. Split `ProjectDashboardController`.
6. Extract invitation actions.
7. Extract task feature actions.
8. Extract project and message actions.
9. Run focused tests after each slice.

## Final Target

After this refactor, the app should have:

- Resource controllers for true resources
- Small controllers for command/query endpoints
- Action classes for mutation workflows with side effects
- Services that orchestrate without becoming dumping grounds
