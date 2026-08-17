# Scramble OpenAPI Documentation — Fix Plan

## Background

The Scramble setup (`ScrambleServiceProvider`) is already excellent. The issues are concentrated in the **controllers** themselves, not the service provider. All fixes are isolated, low-risk, and do not touch business logic.

---

## Phase 1: Code Correctness Fixes (Critical)

These are real bugs — wrong HTTP status codes and inconsistent return types that affect both runtime behavior and Scramble's ability to infer the correct response shape.

> [!CAUTION]
> Fix these first. They are guideline violations and will produce incorrect OpenAPI specs.

### Files to Modify

#### [MODIFY] `ProjectController.php` — `destroy()` returns 200 instead of 204

The `destroy` method currently returns a `200` message response. Per backend guidelines, all `DELETE` endpoints must return `204 No Content`.

```diff
- return $this->respondWithMessage($project->name.' abandoned successfully');
+ return $this->respondNoContent();
```

#### [MODIFY] `TaskController.php` — `show()` returns `TaskResource` instead of `JsonResponse`

The `show` method bypasses the `respondWithData` wrapper, inconsistent with every other method in the project.

```diff
- public function show(Project $project, Task $task): TaskResource
- {
-     $task->loadMissing(['project:id,slug', 'status', 'assignee']);
-     return new TaskResource($task);
- }
+ public function show(Project $project, Task $task): JsonResponse
+ {
+     $task->loadMissing(['project:id,slug', 'status', 'assignee']);
+     return $this->respondWithData(new TaskResource($task));
+ }
```

#### [MODIFY] `TaskController.php` — `destroy()` also returns a message instead of 204

Same issue as `ProjectController::destroy()`.

```diff
- return $this->respondWithMessage('Task deleted successfully.');
+ return $this->respondNoContent();
```

### Verification

After changes, run PHPStan: `vendor/bin/phpstan analyze`

---

## Phase 2: Add `#[Endpoint(operationId)]` Attributes to Public-Facing Endpoints

Tags are automatically resolved by `ScrambleServiceProvider::resolvePublicApiTag()` based on URI patterns. Only add explicit `#[Endpoint(operationId)]` attributes to endpoints that will be used by external developers or called from generated SDKs.

> [!IMPORTANT]
> Focus on public-facing CRUD endpoints and high-value read operations. Internal/admin endpoints can rely on auto-generated operation IDs.

### Convention

- Use PHP 8 attributes: `#[Endpoint(operationId: 'resource.action')]`
- Format: `{resource}.{action}` (e.g., `projects.list`, `tasks.create`)
- Import: `use Dedoc\Scramble\Attributes\Endpoint;`

### Controllers to Annotate (Public-Facing SDK-Relevant Endpoints)

|| Controller | Methods | Operation IDs |
|| :--- | :--- | :--- |
|| `ProjectController` | `index`, `store`, `show`, `update`, `destroy` | `projects.list`, `projects.create`, `projects.show`, `projects.update`, `projects.destroy` |
|| `TaskController` | `index`, `store`, `show`, `update`, `destroy` | `tasks.list`, `tasks.create`, `tasks.show`, `tasks.update`, `tasks.destroy` |
|| `NotificationsController` | `index`, `markAllAsRead`, `destroy`, `updateStatus` | `notifications.list`, `notifications.markAllAsRead`, `notifications.destroy`, `notifications.updateStatus` |
|| `TokenController` | `index`, `store`, `destroy` | `apiTokens.list`, `apiTokens.create`, `apiTokens.destroy` |
|| `Project/ConversationController` | `index`, `store`, `destroy` | `conversations.list`, `conversations.create`, `conversations.destroy` |
|| `Project/ProjectInvitationController` | All methods | `invitations.list`, `invitations.create`, `invitations.show`, `invitations.update`, `invitations.destroy` |
|| `Project/ProjectInsightsController` | All methods | `projects.insights` |
|| `Project/ProjectLimitsController` | All methods | `projects.limits` |
|| `Project/ActivityController` | All methods | `projects.activities` |
|| `Project/StageController` | All methods | `stages.list`, `stages.create`, `stages.show`, `stages.update`, `stages.destroy` |
|| `Task/ArchiveTaskController` | `__invoke` | `tasks.archive` |
|| `Task/RestoreTaskController` | `__invoke` | `tasks.restore` |
|| `Task/AssignTaskMembersController` | `__invoke` | `tasks.assignMembers` |
|| `Task/UnassignTaskMemberController` | `__invoke` | `tasks.unassignMember` |
|| `Task/TaskStatusController` | All methods | `taskStatuses.list`, `taskStatuses.create`, `taskStatuses.show`, `taskStatuses.update`, `taskStatuses.destroy` |
|| `User/UserController` | All methods | `users.list`, `users.create`, `users.show`, `users.update`, `users.destroy` |
|| `User/CurrentUserController` | `__invoke` | `users.current` |
|| `User/AvatarController` | All methods | `users.avatar` |
|| `User/UserInvitationsController` | All methods | `invitations.userList`, `invitations.userCreate`, `invitations.userShow`, `invitations.userUpdate`, `invitations.userDestroy` |
|| `Dashboard/DashboardChartDataController` | `__invoke` | `dashboard.chartData` |
|| `Dashboard/DashboardKpisController` | `__invoke` | `dashboard.kpis` |
|| `Dashboard/DashboardTasksController` | `__invoke` | `dashboard.tasks` |
|| `Dashboard/DashboardActivitiesController` | `__invoke` | `dashboard.activities` |
|| `Dashboard/DashboardProjectsController` | `__invoke` | `dashboard.projects` |
|| `SubscriptionController` | All methods | `subscription.checkout`, `subscription.plans`, `subscription.cancel`, `subscription.status` |
|| `ApiScopeController` | All methods | `apiTokens.scopes` |

### Example Implementation

```php
use Dedoc\Scramble\Attributes\Endpoint;

class ProjectController extends Controller
{
    #[Endpoint(operationId: 'projects.list')]
    public function index(): JsonResponse
    {
        // ...
    }

    #[Endpoint(operationId: 'projects.create')]
    public function store(Request $request): JsonResponse
    {
        // ...
    }

    #[Endpoint(operationId: 'projects.show')]
    public function show(Project $project): JsonResponse
    {
        // ...
    }

    // ... other methods
}
```

### Verification

Generate the spec and visually confirm public-facing operations have stable operation IDs:

```bash
php artisan scramble:export
```

---

## Phase 3: Update Backend Guidelines

Update [`backend-guidelines.md`](file:///c:/Users/Hamza/daywright/.ai/guidelines/backend-guidelines.md) with an explicit API Documentation section so future controllers are written correctly the first time.

> [!NOTE]
> This is a documentation-only change. No code is modified.

### Rules to Add (new Section 24)

```markdown
## 24. API Documentation (Scramble)

- ✅ Public-facing controller methods that will be used by external developers or called from generated SDKs SHOULD have a `#[Endpoint(operationId: 'resource.action')]` attribute (e.g., `projects.list`, `tasks.create`).
- ✅ Tags are automatically resolved by `ScrambleServiceProvider::resolvePublicApiTag()` based on URI patterns. Do NOT manually declare `@tags`.
- ✅ `DELETE` endpoints MUST use `respondNoContent()` (204). Never return a message body on delete.
- ✅ All controller method return types MUST be `JsonResponse`. Never return a plain `Resource` or `ResourceCollection` directly.
- ❌ Never use `@unauthenticated` — the Scramble route filter handles auth exclusion automatically by filtering `session.auth` and `firstParty.auth` routes.
- ❌ Never manually declare `@throws` for 401/403/404/422/429/500 — these are injected globally by `applySharedPublicApiErrorResponses()` in the ScrambleServiceProvider.
```

---

## Execution Order

|| Phase | Effort | Risk | Priority |
|| :--- | :--- | :--- | :--- |
|| **Phase 1** — Code fixes (3 targeted changes) | ~15 min | Low | 🔴 Do first |
|| **Phase 2** — Add `#[Endpoint(operationId)]` attributes (public-facing endpoints) | ~1-2 hrs | None | 🟡 Do second |
|| **Phase 3** — Update backend guidelines | ~20 min | None | 🟢 Do last |
