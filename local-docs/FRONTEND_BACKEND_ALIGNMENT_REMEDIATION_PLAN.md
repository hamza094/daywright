# Frontend Backend Alignment Remediation Plan

Created: 2026-05-22

This plan starts where `docs/API_FRONTEND_ALIGNMENT_IMPLEMENTATION_PLAN.md` ends.
That earlier plan standardized the shared response layer and many major frontend slices, but the final audit still found live frontend code that reads removed transport keys or sends outdated query shapes.

Work through the phases in order.
Do not mix later cleanup phases into the earlier core-domain fixes.

## Goal

- Make every live frontend API consumer speak the current backend contract.
- Remove the remaining legacy response parsing and query aliases.
- Restore a clean build and a repeatable verification gate.
- Reach a clear go or no-go point for the next project phase.

## Out Of Scope

- Redesigning backend responses again.
- Broad frontend architecture work that does not directly close a confirmed contract gap.
- Reopening already validated admin slices unless implementation reveals a concrete live mismatch.

## Ready To Start The Next Project Phase Only When

- `npm run build` passes.
- No live frontend path reads removed keys such as `tasksData`, `meetingsData`, `pending_invitations`, `success`, `redirectUrl`, or top-level `task` and `meeting` resource keys that no longer exist.
- No live frontend path sends removed query shapes such as `request=archived`, `status=pending`, `filter=unread`, or `query=...` where the backend now expects canonical parameters.
- Core project, task, meeting, invitation, notification, OAuth, and insights flows pass targeted smoke tests.
- Any new helper introduced during remediation has focused automated coverage.

## Canonical Frontend Contract Rules

### Response Rules

- Wrapped single resource and create or update responses: use `getObjectData(response)` or `getResponseData(response)` when falsey values matter.
- Wrapped array and reference data: use `getArrayData(response)`.
- Native paginated resources: use `getPaginatedData(response)`.
- Message-only success responses: use `getResponseMessage(response)`.
- Errors: use `parseApiError(error)` and branch only on normalized `message`, `errors`, `code`, and `meta`.

### Query Rules

- Archived tasks: `filter[state]=archived`
- Invitation user search: `search=...`
- Task member search: `search=...`
- Pending project invitations: `filter[status]=pending`
- Notifications:
  - all = omit the filter entirely
  - unread = `filter[status]=unread`
  - read = `filter[status]=read`
- Meeting history: `request=previous` remains valid
- Social login and Zoom redirect endpoints return `data.redirect_url`

### Mutation Rules

- If an endpoint returns only `{ message }`, do not expect a parallel resource payload.
- If the UI needs updated model data after a message-only response, either update local state from known inputs or refetch the authoritative resource or list.
- Keep using `createIdempotentRequest()` only on routes that already apply backend `Idempotent` middleware.

Current task and meeting mutation note:

- Task assign and unassign endpoints now return wrapped task resources.
- Meeting create and update endpoints return wrapped meeting resources.
- Meeting delete, invitation cancel, and member removal remain message-only.

## Phase Summary

| Phase | Focus                                                     | Priority | Gate                                                 |
| ----- | --------------------------------------------------------- | -------- | ---------------------------------------------------- |
| 1     | Build gate and contract freeze                            | Critical | Must finish first                                    |
| 2     | Project bootstrap and task read paths                     | Critical | Must finish before task modal work                   |
| 3     | Task mutation and detail modals                           | Critical | Must finish before collaboration and meeting cleanup |
| 4     | Collaboration, invitations, and meetings                  | High     | Must finish before cross-cutting cleanup             |
| 5     | Notifications, OAuth, insights, and legacy reader cleanup | High     | Must finish before final release gate                |
| 6     | Final audit, tests, and go or no-go gate                  | Critical | Final gate                                           |

## Phase 1 - Build Gate And Contract Freeze

Priority: Critical  
Gate: Must finish first

Why this phase comes first:

- The frontend build is currently red, which blocks the only clean repo-wide verification signal.
- The remaining work spans several domains, so the canonical response and query rules must be frozen before touching those slices.
- This phase prevents the rest of the remediation from reintroducing mixed parsing styles.

Files likely involved:

- `resources/js/components/Authentication/Login.vue`
- `resources/js/components/Authentication/Register.vue`
- `resources/js/components/Authentication/ForgotPassword.vue`
- `resources/js/components/Authentication/ResetPassword.vue`
- `resources/js/components/Authentication/TwoFACode.vue`
- `resources/js/components/Authentication/VerifyPassword.vue`
- `resources/js/utils/apiResponse.js`
- `resources/js/utils/authResponse.js`

Review first:

- `docs/API_FRONTEND_ALIGNMENT_IMPLEMENTATION_PLAN.md`
- `resources/js/utils/apiResponse.js`
- `resources/js/utils/authResponse.js`

Step-by-step tasks:

- [x] Fix the auth asset-import problem so `npm run build` becomes a usable phase gate again.
- [x] Freeze the canonical helper usage rules in this file and do not introduce new ad hoc response readers while implementing later phases.
- [x] Create a short local grep checklist of removed keys and legacy query shapes that must not survive the remediation.
- [x] Confirm every later phase uses the same mapping between endpoint shape and helper choice.

Frozen helper map for every later phase:

- Wrapped single-resource and create or update responses: use `getObjectData(response)`.
- Wrapped responses where falsey scalar values matter: use `getResponseData(response)`.
- Wrapped arrays and reference collections: use `getArrayData(response)`.
- Native paginated resources: use `getPaginatedData(response)`.
- Message-only success responses: use `getResponseMessage(response)`.
- Errors: use `parseApiError(error)` and branch only on normalized `message`, `errors`, `code`, and `meta`.
- Do not add new dual-shape or fallback readers while implementing later phases.

Short local grep checklist for every implementation pass:

- `tasksData`
- `meetingsData`
- `pending_invitations`
- `response.data.task`
- `response.data.meeting`
- `response.data.success`
- `response.data.redirectUrl`
- `request: 'archived'`
- `status: 'pending'`
- `query:` for invitation or member search paths that should now send `search`
- `response.data && response.data.data ? response.data.data : response.data`

Exit criteria:

- `npm run build` passes.
- The repo has one frozen rule set for resource, paginated, message-only, and error responses.
- Later phases can use build and lint as trustworthy validation gates.

Suggested validation:

- `node --test resources/js/utils/apiResponse.test.js resources/js/utils/authResponse.test.js`
- `npm run build`

## Phase 2 - Project Bootstrap And Task Read Paths

Priority: Critical  
Gate: Must finish before task modal work

Why this phase comes second:

- The project page and task lists are foundational read paths.
- Several later mutation flows depend on the project store, task store, and task reference data being correct.
- This phase removes the highest-risk stale read models before task write flows are touched.

Files likely involved:

- `resources/js/store/project`
- `resources/js/store/task`
- `resources/js/store/SingleTask`
- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Project/Panel/Task.vue`
- `resources/js/components/Project/Panel/ArchiveTasks.vue`

Review first:

- `app/Http/Controllers/Api/V1/Project/ProjectController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectLimitsController.php`
- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Requests/Api/V1/Task/TaskIndexRequest.php`
- `app/Http/Controllers/Api/V1/Task/TaskStatusController.php`

Step-by-step tasks:

- [x] Update the project store `loadProject` path to unwrap the wrapped project resource instead of committing the whole response payload.
- [x] Update the project store `refreshLimits` path to read the wrapped limits payload from `/projects/{slug}/limits`.
- [x] Replace `tasksData` parsing in the task store with `getPaginatedData(response)`.
- [x] Replace `request=archived` with `filter[state]=archived` for archived task retrieval.
- [x] Normalize the task store empty-state shape so both success and failure paths expose the same `{ data, meta, links }` contract.
- [x] Update `SingleTask.loadStatuses` to read wrapped `data.statuses` and `data.due_notifies` from the task status index payload.
- [x] Confirm the project page and task screens no longer depend on removed top-level resource keys.

Exit criteria:

- The project page reads project data and project limits from the current backend contract.
- Active and archived task lists render from current paginated responses only.
- Task status and due-notification reference data load from the wrapped response contract.

Suggested validation:

- Smoke test project page load, task list load, archived task load, and task status dropdown initialization.
- `npm run build`

## Phase 3 - Task Mutation And Detail Modals

Priority: Critical  
Gate: Must finish before collaboration and meeting cleanup

Why this phase comes third:

- The remaining task write flows still read removed keys such as `response.data.task`, `response.data.member`, and `response.data.taskMembers`.
- These modals are user-facing and stateful, so misaligned mutation parsing can silently corrupt Vuex state.
- The idempotent task assignment paths should be preserved while the transport contract is corrected.

Files likely involved:

- `resources/js/components/Project/Panel/TaskDetailModal.vue`
- `resources/js/components/Project/Panel/Modal/TopArea.vue`
- `resources/js/components/Project/Panel/Modal/TaskDescription.vue`
- `resources/js/components/Project/Panel/Modal/TaskMembers.vue`
- `resources/js/store/task`
- `resources/js/store/SingleTask`

Review first:

- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Controllers/Api/V1/Task/AssignTaskMembersController.php`
- `app/Http/Controllers/Api/V1/Task/UnassignTaskMemberController.php`
- `app/Http/Controllers/Api/V1/Task/ArchiveTaskController.php`
- `app/Http/Controllers/Api/V1/Task/RestoreTaskController.php`
- `app/Http/Controllers/Api/V1/Task/TaskMemberSearchController.php`

Step-by-step tasks:

- [x] For task update endpoints, read the wrapped task resource through `getObjectData(response)`.
- [x] For message-only task endpoints such as archive, restore, and delete, stop expecting side-channel resource payloads.
- [x] Decide per action whether to update local state from known inputs or refetch the authoritative task or task list after success.
- [x] Update task member search to read collection `data` instead of raw `response.data`.
- [x] Use `getResponseMessage(response)` for task success toasts on message-only endpoints.
- [x] Reconfirm the existing idempotent assign and unassign helper usage after transport parsing is corrected.

Exit criteria:

- Task title, description, status, due-date, assignment, unassignment, archive, restore, and delete flows no longer read removed keys.
- Task member search and assignment work from the current collection contract.
- Task mutation flows preserve current idempotency behavior.

Suggested validation:

- Smoke test every task detail modal action.
- `node --test resources/js/services/IdempotencyRequestService.test.js resources/js/services/idempotencyCoverage.test.js`
- `npm run build`

## Phase 4 - Collaboration, Invitations, And Meetings

Priority: High  
Gate: Must finish before cross-cutting cleanup

Why this phase comes fourth:

- These flows combine query-shape changes, idempotent writes, paginated resources, wrapped resources, and message-only responses.
- They are active collaboration surfaces, so contract drift here is directly user-visible.
- Fixing them after the core project and task slices keeps the dependency graph smaller.

Files likely involved:

- `resources/js/components/Project/Panel/Features.vue`
- `resources/js/components/Project/Meetings/Meeting.vue`
- `resources/js/components/Project/Meetings/MeetingModal.vue`
- `resources/js/components/Project/Meetings/ViewModal.vue`
- `resources/js/store/meeting`
- `resources/js/components/Profile/ProjectInvitation.vue`

Review first:

- `app/Http/Controllers/Api/V1/User/InvitationUserSearchController.php`
- `app/Http/Requests/Api/V1/User/InvitationUserSearchRequest.php`
- `app/Http/Controllers/Api/V1/Project/ProjectInvitationController.php`
- `app/Http/Requests/Api/V1/Project/ProjectInvitationIndexRequest.php`
- `app/Http/Controllers/Api/V1/Project/ZoomMeetingController.php`
- `app/Http/Requests/Api/V1/Zoom/MeetingIndexRequest.php`

Step-by-step tasks:

- [x] Change invitation user search from `query` to `search` and read results from collection `data`.
- [x] Change pending invitation retrieval from `status=pending` to `filter[status]=pending`.
- [x] Read pending invitation lists from `data` instead of `pending_invitations`.
- [x] After invitation create success, read the returned resource correctly or use a local success message without expecting a removed top-level `message` field.
- [x] Align the meeting store to paginated `{ data, meta, links }` responses.
- [x] Align meeting create and update to wrapped meeting resources and meeting delete to a message-only response.
- [x] Align Zoom meeting redirect and initialization flows to `data.redirect_url`.
- [x] Reconfirm create and update meeting idempotent helper coverage after transport parsing changes.

Exit criteria:

- Invitation search and pending-request list pass backend validation.
- Meeting list, detail, create, update, delete, and start or join flows parse only the current contract.
- Collaboration flows no longer rely on removed invitation or meeting keys.

Suggested validation:

- Smoke test invite user, cancel invitation, remove member, meeting list, create meeting, update meeting, delete meeting, and meeting start or join flows.
- `node --test resources/js/services/IdempotencyRequestService.test.js resources/js/services/idempotencyCoverage.test.js`
- `npm run build`

## Phase 5 - Notifications, OAuth, Insights, And Legacy Reader Cleanup

Priority: High  
Gate: Must finish before final release gate

Why this phase comes fifth:

- These are cross-cutting consumers that still mix old and new response shapes.
- Some of them may continue to work today because they are tolerant readers, but that tolerance is exactly what hides real contract drift.
- This phase closes the final non-admin, non-task legacy islands before the go or no-go gate.

Files likely involved:

- `resources/js/store/notifications.js`
- `resources/js/components/UserNotification.vue`
- `resources/js/components/Notification.vue`
- `resources/js/components/Authentication/Login.vue`
- `resources/js/components/Authentication/ZoomAuth.vue`
- `resources/js/components/Project/Meetings/Meeting.vue`
- `resources/js/services/ProjectInsightsService.js`
- `resources/js/mixins/ProjectInsightsMixin.js`
- `resources/js/components/Project/Insights/ProjectInsightsModal.vue`
- `resources/js/components/Dashboard/ActivityCalendar.vue`
- `resources/js/components/Project/Feature/Schedule.vue`
- `resources/js/components/Project/Panel/Chat.vue`
- `resources/js/utils/zoomUtils.js`
- `resources/js/store/conversations`

Review first:

- `app/Http/Requests/Api/V1/Notifications/NotificationIndexRequest.php`
- `app/Enums/NotificationFilter.php`
- `app/Http/Controllers/Api/V1/NotificationsController.php`
- `app/Http/Controllers/Api/Auth/OAuthController.php`
- `app/Http/Controllers/Api/OAuth/ZoomAuthController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectInsightsController.php`
- `app/Http/Resources/Api/V1/Project/ProjectInsightsResource.php`

Step-by-step tasks:

- [x] Encode notification filters as a nested filter bag and treat `all` as no filter instead of a serialized filter value.
- [x] Keep notification index parsing on paginated payloads only.
- [x] Fix social login redirect handling to read `data.redirect_url`.
- [x] Fix Zoom connect callback handling to read a message-only success response.
- [x] Rewrite `ProjectInsightsService` to read the wrapped `data` resource and stop branching on a removed `success` field.
- [x] Remove the remaining live dual-shape fallback readers once the corresponding backend controller contract is confirmed stable.
- [x] Delete or repair stale legacy stores such as `resources/js/store/conversations`; do not leave broken unused modules behind.

Exit criteria:

- Notification filtering passes backend validation for `read`, `unread`, and all.
- OAuth and Zoom connect flows read the current response shape.
- Project insights work from the current wrapped resource contract.
- Live code no longer intentionally accepts both legacy and current response shapes where the backend contract is already frozen.

Suggested validation:

- Smoke test notification filtering, mark-read flows, social login redirect boot, Zoom connect flow, project insights modal, dashboard activity feed, scheduled messages, and chat loading.
- `npm run build`

## Phase 6 - Final Audit, Tests, And Go Or No-Go Gate

Priority: Critical  
Gate: Final gate

Why this phase closes the plan:

- The remaining risk after the implementation phases is regression, not missing initial coverage.
- A final grep, smoke, and build gate is the only honest way to decide whether frontend alignment is complete enough to move to the next project phase.
- This phase turns the contract cleanup into an enforceable release decision.

Step-by-step tasks:

- [x] Run a repo-wide grep sweep for removed response keys and removed query aliases.
- [x] Add or extend focused Node tests for any new domain helper introduced during Phases 2 through 5.
- [x] Add static coverage tests if you introduce new query-builder helpers for tasks, invitations, meetings, or notifications.
- [x] Run lint on every touched frontend file.
- [x] Run a clean production build.
- [ ] Perform a targeted manual smoke pass across project load, task list, task modal actions, invitations, meetings, notifications, social login redirect, Zoom connect, and insights.
- [x] Confirm no implementation phase reopened a previously validated idempotent route.

Current gate result on 2026-05-23:

- Automated gate is green. The Phase 6 grep sweep found no live `resources/js` consumers still using the removed response keys or query aliases from the remediation checklist.
- Focused frontend coverage passed for the shared response helpers, idempotent request coverage, archived task query helper, notification query helper, and project insights response helper.
- ESLint passed for `resources/js/**/*.js` and `resources/js/**/*.vue`.
- `npm run build` passed. The remaining output is the existing large-chunk warning for `public/build/assets/app-*.js`, not a build failure.
- Manual browser smoke is only partially complete. The landing page and login page render correctly, but the public OAuth redirect boot currently returns `500` locally because the throttled `oauth2-socialite` route is running in an environment where Redis is unavailable at `127.0.0.1:6379`.
- Authenticated browser smoke for project load, task actions, invitations, meetings, notifications, Zoom connect, and insights was not completed in this pass because there was no authenticated browser session after the OAuth bootstrap blocker.
- Current decision: no-go for release sign-off from this local Phase 6 run until Redis-backed OAuth bootstrap succeeds and the authenticated browser smoke paths are rerun.

Recommended grep checklist:

- `tasksData`
- `meetingsData`
- `pending_invitations`
- `response.data.task`
- `response.data.meeting`
- `response.data.success`
- `response.data.redirectUrl`
- `request: 'archived'`
- `status: 'pending'`
- `axiosParams.filter = filter`
- `response.data && response.data.data ? response.data.data : response.data`

Exit criteria:

- No live frontend consumer relies on removed response keys.
- No live frontend consumer sends invalid query shapes for standardized endpoints.
- Build, lint, and focused tests pass.
- The repo has a defendable go or no-go answer for starting the next project phase.

Suggested validation:

- `npx eslint resources/js/**/*.js resources/js/**/*.vue`
- `node --test resources/js/utils/apiResponse.test.js resources/js/utils/authResponse.test.js resources/js/services/IdempotencyRequestService.test.js resources/js/services/idempotencyCoverage.test.js`
- `npm run build`

## Implementation Rule Inside Every Phase

Use the same local sequence inside each phase:

1. Review the backend controller and request contract first.
2. Update or add the smallest shared helper needed for that slice.
3. Fix the owning store or service next.
4. Fix the directly dependent component after the owning data layer is correct.
5. Validate the slice immediately before moving on.

## Final Note

This plan is intentionally narrower than `docs/FRONTEND_REMEDIATION_PLAN.md`.
That earlier document is a broader frontend production hardening roadmap.
This remediation plan exists only to finish the remaining frontend and backend contract alignment work so the project can move to its next phase without carrying known API drift forward.
