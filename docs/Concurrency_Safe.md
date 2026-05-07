**Plan**

1. Phase 0. Implemented. Idempotency now relies on the app default cache store, production is expected to run with `CACHE_DRIVER=redis`, the package contract remains explicit in `config/idempotency.php`, and PHPUnit isolates idempotent flows with the testing cache driver.

2. Phase 1. Implemented. `routes/api/v1.php` now applies user-scoped idempotency middleware to subscription create and swap, API token create, meeting create and update, invitation send and accept and reject, project message send, and task assign and unassign. Subscription cancel remains `DELETE /subscriptions` and is intentionally kept out of package-based idempotency because this package version only enforces POST, PUT, and PATCH.

3. Phase 2. Implemented. Zoom webhooks now map Zoom's `x-zm-request-id` header to the configured idempotency header inside VerifyZoomWebhook.php after signature verification, so the global-scope middleware can deduplicate provider retries at the HTTP boundary. The queued Zoom jobs also now share an atomic per-meeting lock in StartMeetingWebhook.php, MeetingEndsWebhook.php, UpdateMeetingWebhook.php, and DeleteMeetingWebhook.php so concurrent deliveries cannot mutate or notify the same meeting at the same time.

4. Phase 3. Add database guarantees for duplicate-prone relationships and external identities. Introduce composite unique constraints in create_project_members_table.php, create_task_user_table.php, and create_message_user_table.php, plus durable meeting uniqueness in create_meetings_table.php.

5. Phase 4. Fix the critical race-prone business actions that middleware alone cannot solve. Prioritize billing in SubscriptionService.php, meeting orchestration in MeetingService.php, task assignment in AssignTaskMembersAction.php, and membership transitions in SendProjectInvitationAction.php, AcceptProjectInvitationAction.php, and RemoveProjectMemberAction.php.

6. Phase 5. Shorten transactions and move side effects out of lock windows. Refactor remote calls, notifications, queue dispatch, and file operations in MeetingService.php, FileService.php, BulkDeleteTasksAction.php, and BulkDeleteProjectsAction.php to use after-commit or smaller transactions.

7. Phase 6. Fix background claim patterns that can still double-send without any client retry key. Rework scheduled message dispatch in ScheduledMessages.php, MessageService.php, CreateProjectMessageAction.php, and DispatchProjectMessageAction.php, plus due-task notifications in TaskNotify.php and TaskDueAction.php.

8. Phase 7. Lock the contract with focused tests. Add route-level idempotency tests for first execution, replay, mismatched payload, and in-flight duplicate, then add domain tests proving no duplicate rows or duplicate side effects under repeated commands for subscriptions, meetings, invitations, tasks, messages, tokens, and webhooks.

9. Phase 8. Defer lower-risk coverage until the production-critical paths are stable. Secondary UI mutations can be added later instead of broadening scope before the high-risk financial and integration routes are proven safe.

**Key Files**

- config/cache.php
- config/idempotency.php
- routes/api/v1.php
- app/Http/Middleware/VerifyZoomWebhook.php
- app/Services/Subscription/PlanLimitService.php
- app/Services/Auth/ApiTokenService.php
- app/Services/Paddle/SubscriptionService.php
- app/Services/Project/MeetingService.php
- app/Actions/Task/AssignTaskMembersAction.php
- app/Actions/Project/AcceptProjectInvitationAction.php
- app/Services/Project/MessageService.php
- app/Console/Commands/ScheduledMessages.php
- app/Console/Commands/TaskNotify.php

**Verification**

1. Prove the package works with the real production cache backend and returns 409 for in-flight duplicates.
2. For each protected route, test first request, replayed request, mismatched payload, and missing-header behavior.
3. For each critical domain write, test that repeated commands cannot create duplicate rows or duplicate side effects.
4. For webhook and scheduler flows, test that duplicate deliveries or overlapping workers do not send duplicate notifications or mutate state twice.

**Key Decisions**

- Use the package as an HTTP retry boundary, not as a replacement for transactions, unique constraints, or row locks.
- Keep DELETE routes naturally idempotent in the service layer instead of depending on this package version.
- Keep user scope for authenticated client commands and global scope only for provider-owned external events.
- Do not broaden coverage to low-risk routes until subscriptions, meetings, invitations, tasks, messages, and webhooks are stable.

1. I can revise this into a stricter production checklist with P0, P1, and P2 priorities.
2. I can break Phase 1 into exact route-by-route middleware recommendations.
3. I can turn Phase 3 and Phase 4 into a migration-and-service hardening plan only.
