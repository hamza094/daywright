# Public Reachability Audit for Custom Exceptions

## Audit Results

### Public-Facing Exceptions

These exceptions are thrown in public API routes and should be documented in OpenAPI:

| Exception                         | Thrown In                                       | Route Context                                               | Status Code | Error Code                           |
| --------------------------------- | ----------------------------------------------- | ----------------------------------------------------------- | ----------- | ------------------------------------ |
| `ArchivedResourceException`       | `Project.php` route binding                     | Implicit model binding for trashed projects/tasks           | 409         | `project_archived` / `task_archived` |
| `InvalidStateTransitionException` | `HasStateMachine.php`                           | State transitions in public API (e.g., task status changes) | 422         | `invalid_state_transition`           |
| `TaskNotTrashedException`         | `DeleteTaskAction.php`, `RestoreTaskAction.php` | Force delete/restore task routes                            | 403         | `task_not_trashed`                   |
| `PlanLimitExceededException`      | `PlanLimitService.php` via factory              | Plan limit enforcement in public API                        | 403         | `plan_limit_exceeded`                |
| `SubscriptionRequiredException`   | `CheckSubscription.php` middleware              | Routes with `subscription` middleware                       | 403         | `subscription_required`              |

### Internal/Admin-Only Exceptions

These exceptions are thrown in admin-only routes and should NOT be documented in public API:

| Exception                   | Thrown In                 | Route Context             | Reason                             |
| --------------------------- | ------------------------- | ------------------------- | ---------------------------------- |
| `DashboardServiceException` | `DashboardController.php` | Admin dashboard endpoints | Admin-only routes (`/api/admin/*`) |

### External Service Exceptions

These exception families may be thrown in public API but require careful mapping:

| Exception Family               | Thrown In                         | Notes                                           |
| ------------------------------ | --------------------------------- | ----------------------------------------------- |
| `ZoomException`                | Zoom integration endpoints        | Map concrete subclasses with fixed status codes |
| `Paddle\SubscriptionException` | Subscription management endpoints | Map concrete subclasses with fixed status codes |

## Route Context Details

### ArchivedResourceException

- **Project binding**: `routes/api/v1/projects/core.php` - implicit model binding for trashed projects
- **Task binding**: `routes/api/v1/projects/tasks.php` - implicit model binding for trashed tasks
- **Public API**: Yes - both are in public API routes

### InvalidStateTransitionException

- **State transitions**: Thrown via `HasStateMachine` trait used by models
- **Public API**: Yes - state changes occur in public API (e.g., task status updates)
- **Examples**: Task status transitions, project stage transitions

### TaskNotTrashedException

- **Force delete**: `Actions/Task/DeleteTaskAction.php` - called from force delete route
- **Restore**: `Actions/Task/RestoreTaskAction.php` - called from restore route
- **Public API**: Yes - these are public API actions

### PlanLimitExceededException

- **Plan limits**: Enforced via `PlanLimitService` in various actions
- **Public API**: Yes - limits enforced when creating projects, tasks, etc.
- **Middleware**: Used in `CheckSubscription` middleware

### SubscriptionRequiredException

- **Middleware**: `Http/Middleware/CheckSubscription.php`
- **Public API**: Yes - used in routes like `routes/api/v1/projects/messages.php`
- **Example**: POST `/v1/projects/{project}/messages` requires active subscription

### DashboardServiceException

- **Admin controller**: `Http/Controllers/Api/V1/Admin/DashboardController.php`
- **Public API**: No - admin-only routes under `/api/admin/*`
- **Action**: Should NOT be documented in public OpenAPI spec

## Recommendations

1. **Document public-facing exceptions** in OpenAPI via `ExceptionToResponseExtension`
2. **Skip DashboardServiceException** in public API documentation (admin-only)
3. **Map concrete Zoom/Paddle subclasses** with fixed status codes (not the base dynamic classes)
4. **Add exception-to-response mappings** in `ScrambleServiceProvider` for public exceptions
