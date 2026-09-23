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

Read `app/Services/Task/TaskService.php`, `app/Services/Project/ProjectService.php`, and `app/Traits/HasStateMachine.php` before editing. Keep the existing service structure.

1. For each state change, load the current task/project inside the transaction with `lockForUpdate()`. Validate and save that locked model. Do not write attributes from a stale route-bound model.
2. Inspect other callers of the state-machine trait. A transition must not bypass the current-state check.
3. For collaborative editing, choose one simple version contract for both task and project: add a version column, return it on reads, require the client's version on updates, and reject stale versions with the existing API error format. Do not add a separate event-sourcing or operation layer.
4. Keep lock order consistent when an operation changes both task and project. Send notifications only for committed business changes.
5. Add focused tests: a stale task snapshot cannot revive a terminal task; the same for a project; two edits using the same version cannot both win. Phase 4 verifies this with separate processes and the production database engine.

If collaborative editing is not part of the first release, restrict those update endpoints and record the feature gate; do not silently drop stale-write protection while leaving them public.

## P2.4: deferred messaging work

When messaging is enabled, make dispatch intent durable, recover expired claims, and track each recipient's result so successful recipients are not resent. Reuse the existing message models and jobs where possible. Test a crash after claim and a mixed success/failure batch. This work is not a gate for a release that does not expose messaging.

## Phase 2 acceptance

- P2.5's locked transitions and stale-version response pass focused tests, or collaborative edits are disabled for release.
- The existing Zoom/Paddle recovery tests pass. Each outbound mutation has an explicit unknown/manual-review path and does not blindly repeat after an ambiguous result.
- Phase 4 proves recovery and contention with the intended database, Redis, scheduler, and workers.
- Record any provider sandbox result separately from local test results. Do not call an unperformed provider check complete.
