**Plan**

1. Phase 0. Implemented. Idempotency now relies on the app default cache store, production is expected to run with `CACHE_DRIVER=redis`, the package contract remains explicit in `config/idempotency.php`, and PHPUnit isolates idempotent flows with the testing cache driver.

2. Phase 1. Implemented. `routes/api/v1.php` now applies user-scoped idempotency middleware to current-user subscription create and update, API token create, meeting create and update, invitation send and accept and reject, project message send, and task assign and unassign. Subscription cancel remains `DELETE /users/me/subscription` and is intentionally kept out of package-based idempotency because this package version only enforces POST, PUT, and PATCH.

3. Phase 2. Implemented. Zoom webhooks now map Zoom's `x-zm-request-id` header to the configured idempotency header inside VerifyZoomWebhook.php after signature verification, so the global-scope middleware can deduplicate provider retries at the HTTP boundary. The queued Zoom jobs also now share an atomic per-meeting lock in StartMeetingWebhook.php, MeetingEndsWebhook.php, UpdateMeetingWebhook.php, and DeleteMeetingWebhook.php so concurrent deliveries cannot mutate or notify the same meeting at the same time.

4. Phase 3. Implemented. A dedicated migration now removes legacy duplicate rows where needed and adds durable unique constraints for project membership pairs, task assignee pairs, message recipient pairs, and Zoom `meeting_id` values. This gives the database a hard guarantee for the duplicate-prone relationships that the application already treats as unique.

5. Phase 4. Implemented. Billing mutations in `SubscriptionService.php` now serialize on the owning user row, meeting create and update and delete flows in `MeetingService.php` now run under the guarded write they depend on, task assignment in `AssignTaskMembersAction.php` now only attaches and notifies newly assigned users, and invitation acceptance and member removal now lock the owning project row so repeated or concurrent transitions cannot duplicate side effects.

6. Phase 5. Implemented. Meeting writes now use short Redis-backed operation locks plus short database transactions so Zoom calls no longer hold row locks open, avatar deletion clears the database state first and deletes S3 objects after commit, task bulk delete now commits per chunk instead of across the full batch, and bulk project deletion no longer wraps queued Zoom cancellation dispatches inside a long outer transaction.

7. Phase 6. Implemented. Scheduled message dispatch now claims each due message under a row lock before batching any jobs, stores the claim in `batch_id`, and only dispatches the batch after commit so overlapping scheduler runs cannot enqueue the same message twice. Due-task notifications now use one locked claim path that marks `notify_sent` exactly once before queued notifications are dispatched, removing the old check-then-send race from `tasks:notify`.

8. Phase 7. Lock the contract with focused tests. Keep this route-level suite concentrated on the package contract and the highest-risk production paths: token mismatch and in-flight duplicate handling, subscription create replay, meeting create replay, and Zoom webhook replay. Invitations, task assignment, and project messaging already have focused feature or action coverage for duplicate side effects, so extra route-replay cases can stay out of this file for now.

9. Phase 8. Defer lower-risk coverage until the production-critical paths are stable. Secondary UI mutations such as invitation send, task assign, and project message send can be widened later instead of broadening this contract suite before the high-risk financial and integration routes are proven safe.

**Key Files**

- config/cache.php
- config/idempotency.php
- routes/api/v1.php
- app/Http/Middleware/VerifyZoomWebhook.php
- database/migrations/2026_05_07_114216_add_phase_three_uniqueness_guarantees.php
- app/Services/Subscription/PlanLimitService.php
- app/Services/Auth/ApiTokenService.php
- app/Services/Paddle/SubscriptionService.php
- app/Services/Project/MeetingService.php
- app/Actions/Task/AssignTaskMembersAction.php
- app/Actions/Project/AcceptProjectInvitationAction.php
- app/Services/Project/MessageService.php
- app/Console/Commands/ScheduledMessages.php
- app/Console/Commands/TaskNotify.php
- tests/Feature/Database/PhaseThreeUniquenessTest.php

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
