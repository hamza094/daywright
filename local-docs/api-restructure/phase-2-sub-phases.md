# Phase 2: Add `#[Endpoint(operationId)]` Attributes — Sub-Phase Plan

This plan breaks down Phase 2 into logical sub-phases for incremental implementation. Each sub-phase focuses on a related group of controllers, allowing for testing and validation before moving to the next group.

> [!NOTE]
> This phase only adds `#[Endpoint(operationId)]` attributes for stable operation IDs. No changes to HTTP status codes or response methods are included in this phase.

---

## Phase 2.1: Core CRUD Controllers (Priority 1)

**Rationale:** Projects and Tasks are the core entities of the application. These endpoints are most likely to be used by external developers and SDK consumers.

### Controllers to Annotate

| Controller          | Methods                                       | Operation IDs                                                                              |
| :------------------ | :-------------------------------------------- | :----------------------------------------------------------------------------------------- |
| `ProjectController` | `index`, `store`, `show`, `update`, `destroy` | `projects.list`, `projects.create`, `projects.show`, `projects.update`, `projects.destroy` |
| `TaskController`    | `index`, `store`, `show`, `update`, `destroy` | `tasks.list`, `tasks.create`, `tasks.show`, `tasks.update`, `tasks.destroy`                |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to both controllers
2. Add `#[Endpoint(operationId: '...')]` attribute to each method
3. Run `php artisan scramble:export` to verify operation IDs appear correctly
4. Check generated OpenAPI spec for Projects and Tasks sections

### Verification

```bash
php artisan scramble:export
# Verify that projects.* and tasks.* operation IDs are present in the spec
```

---

## Phase 2.2: User Management Controllers (Priority 2)

**Rationale:** User-related endpoints are fundamental for any API client and will be heavily used by SDK consumers.

### Controllers to Annotate

| Controller                       | Methods     | Operation IDs                                                                                                                 |
| :------------------------------- | :---------- | :---------------------------------------------------------------------------------------------------------------------------- |
| `User/UserController`            | All methods | `users.list`, `users.create`, `users.show`, `users.update`, `users.destroy`                                                   |
| `User/CurrentUserController`     | `__invoke`  | `users.current`                                                                                                               |
| `User/AvatarController`          | All methods | `users.avatar`                                                                                                                |
| `User/UserInvitationsController` | All methods | `invitations.userList`, `invitations.userCreate`, `invitations.userShow`, `invitations.userUpdate`, `invitations.userDestroy` |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For `UserInvitationsController`, ensure operation IDs distinguish from project invitations
4. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that users.* and invitations.user* operation IDs are present
```

---

## Phase 2.3: Notification & API Token Controllers (Priority 3)

**Rationale:** These are important utility endpoints that API clients will need for managing user notifications and authentication.

### Controllers to Annotate

| Controller                | Methods                                             | Operation IDs                                                                                              |
| :------------------------ | :-------------------------------------------------- | :--------------------------------------------------------------------------------------------------------- |
| `NotificationsController` | `index`, `markAllAsRead`, `destroy`, `updateStatus` | `notifications.list`, `notifications.markAllAsRead`, `notifications.destroy`, `notifications.updateStatus` |
| `TokenController`         | `index`, `store`, `destroy`                         | `apiTokens.list`, `apiTokens.create`, `apiTokens.destroy`                                                  |
| `ApiScopeController`      | All methods                                         | `apiTokens.scopes`                                                                                         |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For `ApiScopeController`, determine appropriate operation ID(s) based on actual methods
4. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that notifications.* and apiTokens.* operation IDs are present
```

---

## Phase 2.4: Project Sub-Controllers (Priority 4)

**Rationale:** These controllers handle project-specific operations and are important for comprehensive project management in SDKs.

### Controllers to Annotate

| Controller                            | Methods                     | Operation IDs                                                                                             |
| :------------------------------------ | :-------------------------- | :-------------------------------------------------------------------------------------------------------- |
| `Project/ConversationController`      | `index`, `store`, `destroy` | `conversations.list`, `conversations.create`, `conversations.destroy`                                     |
| `Project/ProjectInvitationController` | All methods                 | `invitations.list`, `invitations.create`, `invitations.show`, `invitations.update`, `invitations.destroy` |
| `Project/ProjectLimitsController`     | All methods                 | `projects.limits`                                                                                         |
| `Project/ActivityController`          | All methods                 | `projects.activities`                                                                                     |
| `Project/StageController`             | All methods                 | `stages.list`, `stages.create`, `stages.show`, `stages.update`, `stages.destroy`                          |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For single-action controllers (Limits, Activity), use a single operation ID
4. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that conversations.*, invitations.*, projects.*, and stages.* operation IDs are present
```

---

## Phase 2.5: Task Sub-Controllers (Priority 5)

**Rationale:** Task-specific operations round out the task management functionality for SDK consumers.

### Controllers to Annotate

| Controller                          | Methods     | Operation IDs                                                                                                  |
| :---------------------------------- | :---------- | :------------------------------------------------------------------------------------------------------------- |
| `Task/ArchiveTaskController`        | `__invoke`  | `tasks.archive`                                                                                                |
| `Task/RestoreTaskController`        | `__invoke`  | `tasks.restore`                                                                                                |
| `Task/AssignTaskMembersController`  | `__invoke`  | `tasks.assignMembers`                                                                                          |
| `Task/UnassignTaskMemberController` | `__invoke`  | `tasks.unassignMember`                                                                                         |
| `Task/TaskStatusController`         | All methods | `taskStatuses.list`, `taskStatuses.create`, `taskStatuses.show`, `taskStatuses.update`, `taskStatuses.destroy` |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For single-action controllers, add attribute to `__invoke` method
4. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that tasks.* and taskStatuses.* operation IDs are present
```

---

## Phase 2.6: Dashboard & Subscription Controllers (Priority 6)

**Rationale:** Dashboard endpoints provide valuable analytics and subscription endpoints handle billing - both important for complete API coverage.

### Controllers to Annotate

| Controller                                | Methods     | Operation IDs                                                                               |
| :---------------------------------------- | :---------- | :------------------------------------------------------------------------------------------ |
| `Dashboard/DashboardChartDataController`  | `__invoke`  | `dashboard.chartData`                                                                       |
| `Dashboard/DashboardKpisController`       | `__invoke`  | `dashboard.kpis`                                                                            |
| `Dashboard/DashboardTasksController`      | `__invoke`  | `dashboard.tasks`                                                                           |
| `Dashboard/DashboardActivitiesController` | `__invoke`  | `dashboard.activities`                                                                      |
| `Dashboard/DashboardProjectsController`   | `__invoke`  | `dashboard.projects`                                                                        |
| `SubscriptionController`                  | All methods | `subscription.checkout`, `subscription.plans`, `subscription.cancel`, `subscription.status` |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For Dashboard controllers, all are single-action controllers
4. For `SubscriptionController`, determine appropriate operation IDs based on actual methods
5. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that dashboard.* and subscription.* operation IDs are present
```

---

## Phase 2.7: Remaining Project Sub-Controllers (Priority 7)

**Rationale:** These are less frequently used but still important for complete API coverage.

### Controllers to Annotate

| Controller                                  | Methods     | Operation IDs               |
| :------------------------------------------ | :---------- | :-------------------------- |
| `Project/ProjectMemberController`           | All methods | `projects.members`          |
| `Project/UpdateProjectStageController`      | `__invoke`  | `projects.updateStage`      |
| `Project/RestoreProjectController`          | `__invoke`  | `projects.restore`          |
| `Project/AcceptProjectInvitationController` | `__invoke`  | `invitations.acceptProject` |
| `Project/RejectProjectInvitationController` | `__invoke`  | `invitations.rejectProject` |

### Implementation Steps

1. Add `use Dedoc\Scramble\Attributes\Endpoint;` to all controllers
2. Add `#[Endpoint(operationId: '...')]` attributes to each method
3. For single-action controllers, add attribute to `__invoke` method
4. Run `php artisan scramble:export` to verify

### Verification

```bash
php artisan scramble:export
# Verify that projects.* and invitations.* operation IDs are present
```

---

## Execution Order & Timeline

| Sub-Phase     | Controllers                   | Effort  | Priority       |
| :------------ | :---------------------------- | :------ | :------------- |
| **Phase 2.1** | Core CRUD (Projects, Tasks)   | ~15 min | 🔴 Highest     |
| **Phase 2.2** | User Management               | ~20 min | 🔴 High        |
| **Phase 2.3** | Notifications & API Tokens    | ~15 min | 🟡 Medium-High |
| **Phase 2.4** | Project Sub-Controllers       | ~25 min | 🟡 Medium      |
| **Phase 2.5** | Task Sub-Controllers          | ~15 min | 🟡 Medium      |
| **Phase 2.6** | Dashboard & Subscription      | ~15 min | 🟢 Medium-Low  |
| **Phase 2.7** | Remaining Project Controllers | ~15 min | 🟢 Lowest      |

**Total Estimated Time:** ~2 hours

---

## General Implementation Notes

### Import Statement

Add this to the top of every controller you modify:

```php
use Dedoc\Scramble\Attributes\Endpoint;
```

### Attribute Syntax

```php
#[Endpoint(operationId: 'resource.action')]
public function methodName(): JsonResponse
{
    // ...
}
```

### Testing Each Sub-Phase

After completing each sub-phase:

1. Run `php artisan scramble:export`
2. Open the generated OpenAPI spec
3. Search for the expected operation IDs
4. Verify they appear in the correct sections
5. Check that tags are still automatically assigned correctly

### Rollback Strategy

If any sub-phase introduces issues:

- The changes are isolated to specific controllers
- Simply remove the `#[Endpoint]` attributes from the affected controllers
- The automatic operation ID generation will resume

---

## Completion Criteria

Phase 2 is complete when:

- ✅ All 7 sub-phases have been implemented
- ✅ `php artisan scramble:export` generates a valid OpenAPI spec
- ✅ All public-facing endpoints have stable operation IDs
- ✅ Tags are still correctly assigned by the ServiceProvider
- ✅ No PHPStan or linting errors are introduced
