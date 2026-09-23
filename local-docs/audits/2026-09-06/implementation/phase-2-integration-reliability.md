# Phase 2: Integration reliability

This is the current release handoff. The original findings and reproductions remain in [findings-reference.md](findings-reference.md) and the [source audit](../rest-production-audit.md). Check the current code before changing it; the audit's proposed classes are historical suggestions.

## Release scope

| Item                           | Current position                                                                        | Next step                                                                                                    |
| ------------------------------ | --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| P2.1 Zoom webhook inbox        | Implemented                                                                             | Run final integration and recovery checks in Phase 4.                                                        |
| P2.2 Zoom creation recovery    | Implemented                                                                             | Confirm operation ID correlation with a real Zoom sandbox flow; verify recovery in Phase 4.                  |
| P2.3 Paddle Classic            | Cashier handles incoming webhooks; `SubscriptionOperation` handles outgoing swap/cancel | Finish the small release checks in [refactor_paddle.md](refactor_paddle.md). Do not recreate a Paddle inbox. |
| P2.4 Message delivery recovery | Deferred because messaging is unreleased                                                | Complete before enabling messaging. Keep its routes/jobs inaccessible to production users until then.        |
| P2.5 Task and project edits    | Still open                                                                              | Implement the focused change below before exposing collaborative edits.                                      |

Do not add a generic integration operation framework or repeat completed Zoom/Paddle implementation. Current status is based on code and earlier verification, not a claim that sandbox or real worker checks have passed.

## P2.5: finish task and project safety

### ✅ COMPLETED: State Transition Locking (Part 1)

**Implemented database locking for state transitions to prevent race conditions:**

1. **TaskService.php**: Updated `updateTask()` method to:
   - Re-fetch task with `lockForUpdate()` inside transaction using `firstOrFail()` for clear error handling
   - Use the fresh model (`$freshTask`) for all operations including notification reset, state transitions, and updates
   - Return the fresh model with proper eager loading

2. **ProjectService.php**: Updated `updateStageStatus()` method to:
   - Re-fetch project with `lockForUpdate()` inside transaction using `firstOrFail()` for clear error handling
   - Reload the `stage` relationship after state changes to ensure fresh data for `getPostponedReason()`
   - Use the fresh model for all operations and return it with proper eager loading

3. **Code Review**: Verified other state transition callers:
   - Meeting actions (UpdateProjectMeeting, DeleteProjectMeeting) already have proper locking via `MeetingLockOperations` trait
   - Subscription operations (ResolveSubscriptionOperation) already have proper locking with `lockForUpdate()` + `firstOrFail()`
   - All controllers properly use service layer - no direct model state changes found

### ❌ PENDING: Collaborative Editing Versioning (Part 2)

**Still needs implementation for collaborative editing protection:**

1. Add `version` column to `tasks` and `projects` tables via migration
2. Include version in API responses (TaskResource, ProjectResource)
3. Require version on update requests (validation in Request classes)
4. Validate version matches current database version before updating in services
5. Increment version after successful update
6. Return 409 Conflict response for stale requests

### Implementation Notes

- Locking prevents concurrent state transition race conditions during short transactions
- Versioning prevents collaborative editing conflicts over longer user sessions
- Both solutions are complementary and address different concurrency scenarios
- Concurrency tests must run against MySQL/PostgreSQL (not SQLite) since SQLite lacks equivalent row-level locking

## P2.4: deferred messaging work

When messaging is enabled, make dispatch intent durable, recover expired claims, and track each recipient's result so successful recipients are not resent. Reuse the existing message models and jobs where possible. Test a crash after claim and a mixed success/failure batch. This work is not a gate for a release that does not expose messaging.

## Phase 2 acceptance

- P2.5's locked transitions and stale-version response pass focused tests, or collaborative edits are disabled for release.
- The existing Zoom/Paddle recovery tests pass. Each outbound mutation has an explicit unknown/manual-review path and does not blindly repeat after an ambiguous result.
- Phase 4 proves recovery and contention with the intended database, Redis, scheduler, and workers.
- Record any provider sandbox result separately from local test results. Do not call an unperformed provider check complete.
