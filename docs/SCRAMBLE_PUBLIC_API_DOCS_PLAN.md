# Scramble Public API Documentation Plan

Created: 2026-05-12

This plan is the handoff document for improving DayWright's public REST API documentation with Scramble.
It is intentionally scoped to released public endpoints only.

Do not document admin endpoints, unreleased export or messaging endpoints, or Zoom-related endpoints in this plan.

## Scope

This plan covers the public API surface that should appear in the published Scramble docs.

Included areas:

- Authentication endpoints used by public clients and first-party browser flows.
- User profile and account endpoints.
- API token endpoints.
- Subscription endpoints.
- Dashboard endpoints that are public and released.
- Project endpoints except unreleased export and project messaging routes.
- Task endpoints.
- Conversation endpoints.
- Notification endpoints.
- Invitation flows that are part of the public product.
- Shared request, response, resource, and error documentation that supports those endpoints.

Explicitly excluded from this plan:

- Admin routes.
- Project export routes.
- Project message and scheduled message routes.
- Zoom token, Zoom OAuth, Zoom meeting, and Zoom webhook routes.
- Any internal, experimental, or unreleased endpoint that is intentionally hidden today.

## Working Rules

- Treat the runtime API contract as the source of truth, not the current generated docs.
- Prefer letting Scramble infer schemas from FormRequests, Resources, route model binding, and native Laravel responses wherever possible.
- Use wrapper `JsonResource` classes before manual `@response` when Scramble needs a composite payload schema; avoid flattening resources in controllers with `resolve()` or DTO `toArray()`.
- Add manual `@response` only for the remaining controller-boundary gaps, especially multi-shape success responses under the same status code, and keep those annotations minimal.
- Keep docs aligned with the standardized API error envelope:

```json
{
  "message": "Resource not found.",
  "code": "not_found",
  "errors": {},
  "meta": {}
}
```

- Keep public docs focused on released behavior. If a route is intentionally hidden from docs today, do not improve its docs in this plan unless the product scope changes.

## References

- Scramble documentation: https://scramble.dedoc.co/
- Laravel Boost (project MCP tools and docs): use the project's Laravel Boost resources (search-docs / MCP server) for version-specific guidance.

## Main Problems To Fix

### 1. Public docs scope is only partially enforced

Current route filtering already excludes some non-public endpoints in [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php), but the public surface still needs a clear documentation policy.

What needs to happen:

- Keep admin routes excluded.
- Keep export, project messaging, and Zoom routes excluded.
- Confirm the remaining public routes are the only ones being documented.
- Prefer explicit exclusions for non-public controllers where that is clearer than a growing prefix filter.

### 2. Error responses are correct at runtime but not fully documented

Public API errors are normalized centrally in [app/Exceptions/Handler.php](../app/Exceptions/Handler.php) and [app/Exceptions/Support/ApiErrorFormatter.php](../app/Exceptions/Support/ApiErrorFormatter.php), but Scramble will only infer part of that automatically.

What needs to happen:

- Make public endpoint docs show the correct error envelope shape.
- Ensure the common public error statuses are documented consistently: `400`, `401`, `403`, `404`, `405`, `409`, `422`, `429`, `500`, and `503` where relevant.
- Reuse one shared approach instead of repeating manual error blocks across every controller.

### 3. Helper-built success responses lose schema detail

Many controllers return `JsonResponse` through `respondWithData`, `respondCreated`, `respondUpdated`, or `respondWithMessage` in [app/Http/Controllers/Api/ApiController.php](../app/Http/Controllers/Api/ApiController.php). That is fine for runtime, but some of these payloads are too anonymous for Scramble.

What needs to happen:

- Identify endpoints whose success payloads are already inferred correctly from Resources and leave them alone.
- Prefer small wrapper `JsonResource` classes for composite helper payloads before adding controller annotations.
- Add explicit response typing only for endpoints where Scramble still cannot infer the public response shape well enough after the resource layer is cleaned up.

Current guidance:

- `POST /api/v1/login`, `GET /api/v1/users/me`, `POST /api/v1/api-tokens`, and `GET /api/v1/task-statuses` should stay inference-first now that they use dedicated wrapper resources.
- Add manual `@response` only where the runtime endpoint still has more than one successful `200` body and Scramble collapses the response to a generic object.

### 4. Request docs are uneven

Some FormRequests already include useful examples, but many public request classes still need descriptions, examples, defaults, enum hints, or query/body clarification.

What needs to happen:

- Normalize examples and descriptions across public FormRequests.
- Improve query filter documentation for list endpoints.
- Make multipart endpoints clearly documented.
- Clarify alternate query input normalization where runtime accepts multiple shapes.

### 5. Resource docs are uneven

Some public Resources are detailed and Scramble-friendly, while others are sparse or rely on arrays that are under-documented.

What needs to happen:

- Add field descriptions and examples where they materially improve the generated docs.
- Strengthen under-documented public resources before adding more manual controller annotations.
- Keep response field descriptions close to the resource class whenever possible.

### 6. Some public endpoints have doc blockers or mismatches

These should be fixed before broad documentation cleanup because they can mislead Scramble or produce incorrect docs.

Known blockers:

- The password reset token route in [routes/auth/v1.php](../routes/auth/v1.php) points to `VerificationController::resetForm`, but that method is not present in [app/Http/Controllers/Api/Auth/VerificationController.php](../app/Http/Controllers/Api/Auth/VerificationController.php).
- [app/Http/Controllers/Api/Auth/SpaAuthController.php](../app/Http/Controllers/Api/Auth/SpaAuthController.php) and [app/Http/Controllers/Api/Auth/OAuthController.php](../app/Http/Controllers/Api/Auth/OAuthController.php) can each return either a two-factor challenge payload or an authenticated session payload under the same `200` response. Scramble currently reduces these routes to a generic object unless they get minimal manual `@response` documentation.

### 7. Public schema names may become noisy

There are duplicate resource basenames across namespaces, for example public and admin variants of `ProjectResource`, `TaskResource`, and `StageResource`.

Even though admin docs are out of scope, naming the public schemas explicitly is still useful because it keeps the generated OpenAPI output predictable and readable.

## Public Routes In Scope

Use these route groups as the implementation baseline for public docs work.

### Auth and session

- `POST /api/v1/register`
- `POST /api/v1/login`
- `POST /api/v1/logout`
- `POST /api/v1/forgot-password`
- `POST /api/v1/reset-password`
- `POST /api/v1/email/verify/{user}`
- `POST /api/v1/email/resend/{user}`
- `POST /api/v1/session/login`
- `POST /api/v1/session/logout`
- `GET /api/v1/auth/redirect/{provider}`
- `GET /api/v1/auth/callback/{provider}`
- `POST /api/v1/twofactor/login-confirm`
- `POST /api/v1/twofactor/setup`
- `POST /api/v1/twofactor/confirm`
- `GET /api/v1/twofactor/fetch-user`
- `GET /api/v1/twofactor/recovery-codes`
- `DELETE /api/v1/twofactor/disable`

### Current user and users

- `GET /api/v1/users/me`
- `GET /api/v1/users`
- `GET /api/v1/users/{user}`
- `PUT|PATCH /api/v1/users/{user}`
- `DELETE /api/v1/users/{user}`
- `DELETE /api/v1/users/{user}/force`
- `POST /api/v1/users/{user}/avatar`
- `DELETE /api/v1/users/{user}/avatar`
- `GET /api/v1/users/search`
- `GET /api/v1/me/invitations`

### Tokens and subscription

- `GET /api/v1/api-tokens`
- `POST /api/v1/api-tokens`
- `DELETE /api/v1/api-tokens/{token}`
- `POST /api/v1/subscriptions`
- `PATCH /api/v1/subscriptions`
- `DELETE /api/v1/subscriptions`
- `GET /api/v1/user/subscriptions`

### Dashboard and notifications

- `GET /api/v1/dashboard/chart-data`
- `GET /api/v1/dashboard/insights`
- `GET /api/v1/dashboard/tasks`
- `GET /api/v1/dashboard/activities`
- `GET /api/v1/dashboard/projects`
- `GET /api/v1/notifications`
- `PATCH /api/v1/notifications/read`
- `PATCH /api/v1/notifications/{notification}/status`
- `DELETE /api/v1/notifications/{notification}`

### Projects, tasks, conversations, invitations

- Public project CRUD routes except export and project messaging endpoints.
- Public stage listing route.
- Public project insights, project limits, activity, member, invitation, and conversation endpoints that are already released.
- Public task CRUD and task member assignment/search flows that are already released.

Do not add export, project message, scheduled message, Zoom meeting, or Zoom callback routes to the plan.

## Target Documentation Quality

The generated public docs should satisfy all of the following:

- Every released public endpoint has a clear summary and description.
- Every released public endpoint shows the correct auth requirement.
- Every released public endpoint shows the correct request parameters, request body, and media type.
- Resource and collection responses show real field names and useful examples.
- Action-only endpoints show the real message-only success payload when applicable.
- Common error responses show the actual public error envelope, not a generic `{ "message": "..." }` shape.
- Query parameters, filters, and pagination behavior are understandable without reading controller code.
- Path parameters use the correct route key type and a useful description.

## Phase Plan

### Phase 0 - Freeze Public Scope and Fix Blockers

Goal:

- Make sure only released public routes are being improved.
- Resolve routing and annotation problems that would make the docs misleading.

Tasks:

- Review the route filter in [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) and confirm it still excludes:
  - admin routes
  - export route
  - project messaging routes
  - Zoom-related routes
- Fix or intentionally exclude the stale password reset token route.
- Align public controller method parameter names with route placeholders where needed.
- Mark guest-access public endpoints as unauthenticated where Scramble needs help.
- Decide whether any currently included route is still not meant for public docs and exclude it now.

Exit criteria:

- Public route list is intentional and stable.
- No stale or misleading route remains in the generated public docs.

### Phase 1 - Establish Shared Scramble Foundations

Goal:

- Build the minimum shared documentation infrastructure so later phases stay small and consistent.

Tasks:

- Add one shared approach for documenting the public error envelope.
- Decide where manual response typing belongs when inference is insufficient:
  - resource class field docs
  - wrapper `JsonResource` classes for composite success payloads
  - controller method `@response` only for multi-shape or still-anonymous responses after resource cleanup
- Add explicit schema names for public resources that need stable names.
- Add grouping and tag conventions for the public docs so one-action controllers do not create a noisy table of contents.
- Document the public bearer-token default and use `@unauthenticated` only where appropriate.

Exit criteria:

- Public docs show a consistent auth model.
- There is one repeatable pattern for public error docs.
- There is one repeatable pattern for helper-built success responses.

### Phase 2 - Authentication, Session, and Current User Endpoints

Goal:

- Clean up the most visible public endpoints first.

Why this phase comes early:

- These endpoints are high-traffic.
- They mix multiple success payload shapes.
- They define the auth story for the entire API.

Tasks:

- Improve docs for token login, session login, OAuth login, logout, registration, password reset, email verification, and 2FA flows.
- Explicitly document alternate success bodies where one endpoint can return more than one public success shape.
- Add minimal manual `@response` annotations for the auth endpoints that still collapse to generic object responses after resource cleanup, currently `POST /session/login` and `GET /auth/callback/{provider}`.
- Ensure validation and auth failure responses show the correct public envelope.
- Improve [app/Http/Requests/Api/V1/Auth](../app/Http/Requests/Api/V1/Auth) request classes with examples and descriptions.
- Improve [app/Http/Resources/Api/V1/User/AuthenticatedUserResource.php](../app/Http/Resources/Api/V1/User/AuthenticatedUserResource.php).

High-priority endpoints in this phase:

- `POST /register`
- `POST /login`
- `POST /session/login`
- `GET /auth/callback/{provider}`
- `POST /twofactor/login-confirm`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /email/verify/{user}`
- `POST /email/resend/{user}`
- `GET /users/me`

### Phase 3 - Tokens, Subscription, and User Account Endpoints

Goal:

- Complete the account-management part of the public API.

Tasks:

- Improve token list, create, and delete docs.
- Improve subscription create, swap, cancel, and current subscription docs.
- Improve user profile, avatar, deletion, and invitations docs.
- Add missing examples and descriptions to relevant public resources and request classes.

High-priority files to touch in this phase:

- [app/Http/Controllers/Api/V1/TokenController.php](../app/Http/Controllers/Api/V1/TokenController.php)
- [app/Http/Requests/Api/V1/UserTokenRequest.php](../app/Http/Requests/Api/V1/UserTokenRequest.php)
- [app/Http/Resources/Api/V1/TokenResource.php](../app/Http/Resources/Api/V1/TokenResource.php)
- [app/Http/Controllers/Api/V1/SubscriptionController.php](../app/Http/Controllers/Api/V1/SubscriptionController.php)
- [app/Http/Requests/Api/V1/SubscriptionRequest.php](../app/Http/Requests/Api/V1/SubscriptionRequest.php)
- [app/Http/Resources/Api/V1/SubscriptionResource.php](../app/Http/Resources/Api/V1/SubscriptionResource.php)
- [app/Http/Controllers/Api/V1/User/UserController.php](../app/Http/Controllers/Api/V1/User/UserController.php)

Exit criteria:

- Account-management routes have complete request and response docs.
- Token and subscription payloads are no longer anonymous in the spec.

### Phase 4 - Dashboard, Notifications, and Read-Heavy List Endpoints

Goal:

- Improve public list, filter, pagination, and collection docs.

Tasks:

- Improve request docs for dashboard filters, notification filters, page parameters, and per-page limits.
- Ensure collection endpoints document pagination and top-level meta accurately.
- Improve resource field docs for notifications and dashboard responses where current arrays are too vague.

High-priority files to touch in this phase:

- [app/Http/Controllers/Api/V1/DashboardProjectsController.php](../app/Http/Controllers/Api/V1/DashboardProjectsController.php)
- [app/Http/Controllers/Api/V1/DashboardChartDataController.php](../app/Http/Controllers/Api/V1/DashboardChartDataController.php)
- [app/Http/Controllers/Api/V1/DashboardTasksController.php](../app/Http/Controllers/Api/V1/DashboardTasksController.php)
- [app/Http/Controllers/Api/V1/DashboardActivitiesController.php](../app/Http/Controllers/Api/V1/DashboardActivitiesController.php)
- [app/Http/Controllers/Api/V1/NotificationsController.php](../app/Http/Controllers/Api/V1/NotificationsController.php)
- [app/Http/Requests/Api/V1/NotificationIndexRequest.php](../app/Http/Requests/Api/V1/NotificationIndexRequest.php)
- [app/Http/Resources/Api/V1/NotificationResource.php](../app/Http/Resources/Api/V1/NotificationResource.php)

Exit criteria:

- Public list endpoints show usable filter and pagination docs.
- Dashboard and notification responses are understandable without backend knowledge.

### Phase 5 - Projects, Tasks, Conversations, and Invitations

Goal:

- Finish the released collaboration surface while keeping unreleased features excluded.

Tasks:

- Improve project CRUD docs and route parameter descriptions.
- Improve public task CRUD docs, including archived filter behavior.
- Improve conversation upload and delete docs, including multipart request behavior.
- Improve invitation and member-management docs for released routes.
- Strengthen resource docs for project, task, conversation, invitation, and related summary resources.

High-priority files to touch in this phase:

- [app/Http/Controllers/Api/V1/Project/ProjectController.php](../app/Http/Controllers/Api/V1/Project/ProjectController.php)
- [app/Http/Controllers/Api/V1/Task/TaskController.php](../app/Http/Controllers/Api/V1/Task/TaskController.php)
- [app/Http/Controllers/Api/V1/Project/ConversationController.php](../app/Http/Controllers/Api/V1/Project/ConversationController.php)
- [app/Http/Controllers/Api/V1/Project/ProjectInvitationController.php](../app/Http/Controllers/Api/V1/Project/ProjectInvitationController.php)
- [app/Http/Requests/Api/V1/ProjectStoreRequest.php](../app/Http/Requests/Api/V1/ProjectStoreRequest.php)
- [app/Http/Requests/Api/V1/ProjectUpdateRequest.php](../app/Http/Requests/Api/V1/ProjectUpdateRequest.php)
- [app/Http/Requests/Api/V1/TaskIndexRequest.php](../app/Http/Requests/Api/V1/TaskIndexRequest.php)
- [app/Http/Requests/Api/V1/TaskRequest.php](../app/Http/Requests/Api/V1/TaskRequest.php)
- [app/Http/Requests/Api/V1/TaskUpdateRequest.php](../app/Http/Requests/Api/V1/TaskUpdateRequest.php)
- [app/Http/Requests/Api/V1/ConversationRequest.php](../app/Http/Requests/Api/V1/ConversationRequest.php)
- [app/Http/Resources/Api/V1/Project](../app/Http/Resources/Api/V1/Project)
- [app/Http/Resources/Api/V1/Task](../app/Http/Resources/Api/V1/Task)
- [app/Http/Resources/Api/V1/ConversationResource.php](../app/Http/Resources/Api/V1/ConversationResource.php)

Exit criteria:

- Released collaboration endpoints are fully documented.
- Export, messaging, and Zoom endpoints are still excluded from the public spec.

### Phase 6 - Final Pass and Verification

Goal:

- Make the published public docs consistent and ready to hand off.

Tasks:

- Review summaries, descriptions, tags, and examples for consistency.
- Regenerate the OpenAPI spec and inspect the generated public docs UI.
- Check that excluded routes do not leak into the public spec.
- Check that multipart endpoints render correctly.
- Check that auth requirements and guest exceptions render correctly.
- Check that public error responses are shown consistently across the main endpoint families.

Exit criteria:

- Public docs match released behavior.
- No excluded routes appear in the published public spec.
- Generated docs are usable as the canonical public API reference.

## Success Definition

This plan is complete when:

- The public API documentation is accurate, readable, and intentionally scoped.
- The generated docs reflect released public behavior only.
- The shared documentation patterns are stable enough that future endpoint additions do not require ad hoc fixes.
- Admin, export, project messaging, and Zoom docs remain out of the public spec until product scope changes.
