# API Frontend Alignment Implementation Plan

Created: 2026-05-21

This plan converts the recent backend API contract changes into a phased frontend implementation roadmap.
Work through the phases in order. Do not mix later admin or cleanup work into the earlier contract and auth phases.

## Why This Plan Exists

- The backend now standardizes helper-backed success responses as `{ data: ... }`.
- Native paginated Laravel resource responses now return `{ data, meta, links }`.
- API errors are standardized as `{ message, code, errors, meta }`.
- Several Vue stores and components still read older transport keys such as `user`, `project`, `projects`, `projectsCount`, `subscription`, `token`, `stage`, `status`, `success`, and `error_type`.
- Query request validation is now stricter, especially for nested `filter[...]`, canonical `sort`, and `page/per_page` semantics.

## Goals

- Align the frontend to the backend's current API contract without weakening the backend standards.
- Phase the work so each implementation slice is small enough to test safely.
- Remove ad hoc response parsing from components and stores.
- Preserve current backend idempotency guarantees and make frontend usage explicit.

## Non-Goals

- Do not redesign the backend contract again during this implementation unless a current contract is internally inconsistent.
- Do not add a global frontend response-unwrapping layer that hides the difference between helper-wrapped resources, native paginated resources, and message-only responses.
- Do not add global `Idempotency-Key` headers to every request.

## Target Contract Reminders

1. Single resource and create/update responses

```json
{
  "data": { ... }
}
```

2. Paginated collections

```json
{
  "data": [ ... ],
  "meta": { ... },
  "links": { ... }
}
```

3. Message-only success responses

```json
{
  "message": "..."
}
```

4. Error responses

```json
{
  "message": "...",
  "code": "...",
  "errors": { ... },
  "meta": { ... }
}
```

## Idempotency Decision

Short answer:

- Do not implement `Idempotency-Key` globally across the frontend.
- Keep using it only for client-initiated routes that are protected by backend `Idempotent` middleware.
- The frontend already has the correct reusable helper in `resources/js/services/IdempotencyRequestService.js`.
- The immediate gap is governance and verification, not a missing first implementation.

### Current Backend Rule

- `config/idempotency.php` currently sets `required` to `true`.
- That requirement matters only on routes that actually apply the backend `Idempotent` middleware.
- Routes without that middleware do not need a frontend `Idempotency-Key` header today.

### Current Client Coverage

The frontend already sends `Idempotency-Key` for the current client-triggered routes protected by backend idempotency middleware:

| Route                                             | Backend idempotent | Frontend coverage                     | Status          |
| ------------------------------------------------- | ------------------ | ------------------------------------- | --------------- |
| `POST /users/me/subscription`                     | Yes                | `Subscription.vue`                    | Already covered |
| `PATCH /users/me/subscription`                    | Yes                | `Subscription.vue`                    | Already covered |
| `POST /api-tokens`                                | Yes                | `UserTokens.vue`                      | Already covered |
| `POST /projects/{project}/messages`               | Yes                | `Project/Feature/Message.vue`         | Already covered |
| `PATCH /projects/{project}/tasks/{task}/assign`   | Yes                | `Project/Panel/Modal/TaskMembers.vue` | Already covered |
| `PATCH /projects/{project}/tasks/{task}/unassign` | Yes                | `Project/Panel/TaskDetailModal.vue`   | Already covered |
| `POST /projects/{project}/invitations`            | Yes                | `Project/Panel/Features.vue`          | Already covered |
| `POST /projects/{project}/invitations/accept`     | Yes                | `Profile/ProjectInvitation.vue`       | Already covered |
| `POST /projects/{project}/invitations/reject`     | Yes                | `Profile/ProjectInvitation.vue`       | Already covered |
| `POST /projects/{project}/meetings`               | Yes                | `Project/Meetings/MeetingModal.vue`   | Already covered |
| `PATCH /projects/{project}/meetings/{meeting}`    | Yes                | `Project/Meetings/ViewModal.vue`      | Already covered |

### Not Currently Required In Frontend

These currently use plain axios, and that is correct unless the backend adds `Idempotent` middleware later:

- `POST /session/login`
- `POST /session/logout`
- `POST /twofactor/login-confirm`
- `POST /twofactor/setup`
- `POST /twofactor/confirm`
- `POST /email/verify/{user}`
- `POST /email/resend/{user}`
- `POST /projects`
- `PATCH /projects/{project}`
- `PATCH /projects/{project}/stage`
- `POST /users/{user}/avatar`
- `PATCH /users/{user}`
- `POST /admin/backup/database`
- `POST /admin/stages`
- `POST /admin/statuses`
- `PATCH /admin/users/{user}/role`

### Idempotency Rule Going Forward

- Whenever backend `Idempotent` middleware is added to a client-initiated route, switch that frontend action to a dedicated `createIdempotentRequest()` instance.
- Keep one helper instance per logical submit action.
- Call `reset()` on modal, form, or component teardown.
- Reuse the same key only for the exact same logical request after a network failure or package retry conflict.

## Phase Summary

| Phase | Focus                                                         | Priority | Gate                                  |
| ----- | ------------------------------------------------------------- | -------- | ------------------------------------- |
| 1     | Contract freeze and shared frontend response layer            | Critical | Must finish first                     |
| 2     | Auth, session, verification, and 2FA alignment                | Critical | Must finish before broad UI rollout   |
| 3     | User-facing reads, pagination, and filter migration           | Critical | Must finish before write-flow polish  |
| 4     | User-facing write flows and resource updates                  | High     | Must finish before admin cleanup      |
| 5     | Admin routes, filters, and CRUD alignment                     | High     | Must finish before final verification |
| 6     | Idempotency governance, regression coverage, and release gate | High     | Final gate                            |

## Phase 1 - Contract Freeze And Shared Frontend Response Layer

Priority: Critical  
Gate: Must finish first

Why this phase comes first:

- The same contract mismatch is currently repeated across many files.
- Fixing individual components before the parsing rules are frozen will create rework.
- Error handling and paginated collection parsing need one consistent frontend strategy.

Main backend rules to freeze in this phase:

- Helper-backed success responses are read from `response.data.data`.
- Paginated resource collections are read from `response.data.data`, `response.data.meta`, and `response.data.links`.
- Message-only success responses are read from `response.data.message`.
- Standardized API errors are read from `response.data.message`, `response.data.code`, `response.data.errors`, and `response.data.meta`.

Step-by-step tasks:

- [x] Create a small response utility layer in `resources/js/services/` or `resources/js/utils/`.
- [x] Add helper functions for reading resource, paginated, and message-only responses without hiding their differences.
- [x] Update `resources/js/mixins/errorHandling.js` to read `code` and `meta` instead of `error_type`.
- [x] Freeze the exact 2FA challenge contract: either keep the current bare resource response or wrap it under `data`, but make backend code, frontend code, and Scramble docs match.
- [x] Freeze the verification failure contract: decide whether frontend should branch on backend error `code` values or just show message-level UX.
- [x] Create a lightweight endpoint matrix listing which endpoints are helper-wrapped, paginated resource responses, or message-only.
- [x] Stop adding direct `response.data.<old_key>` parsing in new work after this phase starts.

Likely files involved:

- `resources/js/mixins/errorHandling.js`
- `resources/js/services/IdempotencyRequestService.js`
- new shared response parsing utility files under `resources/js/services/` or `resources/js/utils/`
- `app/Http/Controllers/Api/Auth/SpaAuthController.php`
- `app/Services/Auth/LoginUserService.php`
- `app/Http/Controllers/Api/Auth/VerificationController.php`

Exit criteria:

- One shared frontend parsing approach exists for resource, paginated, message, and error responses.
- The 2FA challenge contract is explicitly documented and no longer ambiguous.
- The verification screen strategy is decided before auth UI implementation begins.

## Phase 2 - Auth, Session, Verification, And 2FA Alignment

Priority: Critical  
Gate: Must finish before broad UI rollout

Why this phase comes second:

- `currentUser` bootstrap is a root dependency for the entire app.
- The session login and two-factor branches are currently reading the wrong shapes.
- Verification and 2FA pages are among the highest-risk user-facing regressions.

Step-by-step tasks:

- [ ] Update `resources/js/store/currentUser` so helper-backed login success reads `response.data.data.user` and `response.data.data.features`.
- [ ] Update the same store so `/users/me` bootstrap reads `response.data.data.user` and `response.data.data.features`.
- [ ] Update the 2FA login branch to look for `two_factor_state` from the agreed Phase 1 contract instead of `status`.
- [ ] Update `resources/js/components/Authentication/TwoFACode.vue` to read the 2FA fetch-user response from `data.two_factor_state`.
- [ ] Update `resources/js/components/Profile/TwoFactorAuth.vue` to read `data.two_factor_state`, `data.qr_code`, and `data.recovery_codes`.
- [ ] Replace camelCase assumptions such as `recoveryCodes` with the backend's snake_case contract unless a local adapter normalizes that explicitly.
- [ ] Update `resources/js/components/Authentication/VerifyPassword.vue` to read `data.verified` on success.
- [ ] Update verification failure handling to use the Phase 1 error contract decision.
- [ ] Verify `POST /email/resend/{user}` remains message-only and update all callers accordingly.

Likely files involved:

- `resources/js/store/currentUser`
- `resources/js/components/Authentication/VerifyPassword.vue`
- `resources/js/components/Authentication/TwoFACode.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/Dashboard/Dashboard.vue`

Exit criteria:

- Session login works for both direct success and 2FA-required responses.
- Browser refresh correctly bootstraps the authenticated user.
- 2FA status, setup, confirm, recovery-code fetch, and disable flows all read the current backend contract correctly.
- Verification and resend flows no longer depend on removed `status` fields.

## Phase 3 - User-Facing Reads, Pagination, And Filter Migration

Priority: Critical  
Gate: Must finish before write-flow polish

Why this phase comes third:

- Many read paths are still failing because they expect legacy payload keys.
- Query validation is stricter now, so several pages are sending invalid parameters even before they parse the response.
- List and dashboard pages should be stable before mutation UX is refined.

Step-by-step tasks:

- [ ] Update dashboard projects to read the native paginated/collection response shape from top-level `data` and `meta.total`.
- [ ] Update dashboard tasks to send nested `filter[user_created]`, `filter[task_assigned]`, `filter[completed]`, `filter[overdue]`, and `filter[remaining]`.
- [ ] Update dashboard tasks to consume `response.data.data` and `response.data.meta` using the shared parser.
- [ ] Update the projects page to send nested project filters such as `filter[search]`, `filter[member]`, and `filter[abandoned]`.
- [ ] Keep project list sort tokens canonical, for example `-created_at`, `created_at`, `name`, and `-name`.
- [ ] Update the projects page to read the collection response from top-level `data`, `meta`, and `links` instead of `projects` and `projectsCount`.
- [ ] Update pending invitation listing to read the paginated collection returned by `/users/me/invitations`.
- [ ] Update profile and user-detail reads that still expect `response.data.user` to read the current resource shape.
- [ ] Update token list reads that still expect `response.data.tokens` to use the native collection contract.

Likely files involved:

- `resources/js/components/Dashboard/Dashboard.vue`
- `resources/js/components/Dashboard/TasksData.vue`
- `resources/js/components/Projects.vue`
- `resources/js/components/Profile/ProjectInvitation.vue`
- `resources/js/components/Profile/ProfilePage.vue`
- `resources/js/components/Profile/UserTokens.vue`

Exit criteria:

- Dashboard and projects screens load without legacy key assumptions.
- No user-facing read screen sends top-level filter aliases that the backend now rejects.
- Paginated pages consistently read `data`, `meta`, and `links` through the shared parser.

## Phase 4 - User-Facing Write Flows And Resource Updates

Priority: High  
Gate: Must finish before admin cleanup

Why this phase comes after read-path stabilization:

- These flows need the shared parsing rules from earlier phases.
- Several of these mutations now succeed on the backend but still update the UI using removed keys.
- This phase cleans up the highest-value interactive flows without mixing in admin work.

Step-by-step tasks:

- [ ] Update subscription create to read `response.data.data.paylink`.
- [ ] Update subscription show, swap, and cancel flows to read the subscription resource from `response.data.data`.
- [ ] Stop expecting a success `message` on subscription update and destroy unless the backend contract is intentionally changed.
- [ ] Update invitation accept and reject flows to read `response.data.data.project` and `response.data.data.invitation_state`.
- [ ] Use local success copy for invitation accept and reject if the response remains message-less.
- [ ] Update token creation to read `response.data.data.token` and `response.data.data.token_resource`.
- [ ] Keep token delete as a message-only flow.
- [ ] Update avatar upload to read `response.data.data.avatar`.
- [ ] Update profile edit to read the updated user resource from `response.data.data`.
- [ ] Update project creation to read the new project resource from `response.data.data`.
- [ ] Update project name/about/stage update screens to read helper-wrapped project resources from `response.data.data`.
- [ ] Sweep remaining user-facing mutation handlers for stale assumptions such as `response.data.project`, `response.data.user`, `response.data.stage`, or `response.data.status`.

Likely files involved:

- `resources/js/components/Subscription.vue`
- `resources/js/store/subscribeUser.js`
- `resources/js/components/Profile/ProjectInvitation.vue`
- `resources/js/components/Profile/UserTokens.vue`
- `resources/js/components/Profile/Avatar.vue`
- `resources/js/components/Profile/Edit.vue`
- `resources/js/components/ProjectForm.vue`
- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Project/Stage.vue`

Exit criteria:

- All user-facing writes update UI state from the current backend contract.
- No mutation screen depends on removed `message`, `status`, or top-level resource keys.
- Subscription, token, invitation, avatar, and project update flows are stable end to end.

## Phase 5 - Admin Routes, Filters, And CRUD Alignment

Priority: High  
Gate: Must finish before final verification

Why admin is a separate phase:

- The admin screens have the most combined response-shape and query-shape drift.
- Admin work should not block auth and primary user flows.
- It includes the heaviest filter migration work.

Step-by-step tasks:

- [ ] Change admin backup from `GET` to `POST`.
- [ ] Keep admin backup as a message-only success flow.
- [ ] Update admin projects to send nested filters: `filter[state]`, `filter[search]`, `filter[members]`, `filter[status]`, `filter[tasks]`, `filter[stage]`, `filter[from]`, and `filter[to]`.
- [ ] Update admin projects sort handling to use canonical tokens such as `-created_at`, `created_at`, `name`, `-name`, `health_score`, and `-health_score` instead of `asc` or `desc`.
- [ ] Update admin projects to read the paginated response from top-level `data`, `meta`, and `links` and to read applied filters from `meta.applied_filters`.
- [ ] Update admin tasks to send `filter[state]` and `filter[search]` instead of top-level aliases.
- [ ] Update admin tasks to read the native paginated response shape directly.
- [ ] Update admin users to send `filter[search]` instead of top-level search.
- [ ] Update admin users to read the native paginated response shape directly.
- [ ] Update admin role mutation to read the updated resource from `response.data.data` and stop expecting a top-level `user` key.
- [ ] Update stage and status stores so index reads `response.data.data`, create/update reads `response.data.data`, and delete reads `response.data.message`.
- [ ] Update admin stage and status components to stop expecting `success`, `stage`, or `status` at the top level.

Likely files involved:

- `resources/js/components/Admin/Dashboard.vue`
- `resources/js/components/Admin/Projects.vue`
- `resources/js/components/Admin/Tasks.vue`
- `resources/js/components/Admin/Users.vue`
- `resources/js/components/Admin/Stage.vue`
- `resources/js/components/Admin/TaskStatus.vue`
- `resources/js/store/stage`
- `resources/js/store/status`

Exit criteria:

- Admin backup uses the correct method.
- Admin filters pass backend validation with the new query contract.
- Stage, status, role update, and admin list screens all parse the new response shapes correctly.

## Phase 6 - Idempotency Governance, Regression Coverage, And Release Gate

Priority: High  
Gate: Final gate

Why this phase closes the plan:

- The frontend already has idempotency support on the routes that currently need it.
- The remaining work is to make the behavior deliberate, verifiable, and hard to regress.
- This phase also validates that earlier contract changes did not introduce hidden breakage.

Step-by-step tasks:

- [ ] Verify every route currently using backend `Idempotent` middleware still has matching frontend helper usage where the request originates from the client UI.
- [ ] Add a short developer note near `IdempotencyRequestService.js` or in docs explaining when to use it and when not to use it.
- [ ] Do not add a global axios interceptor that injects `Idempotency-Key` on all requests.
- [ ] Add a review checklist item: when backend adds `Idempotent` middleware, frontend must migrate that action to `createIdempotentRequest()` in the same change set.
- [ ] Run focused manual duplicate-submit checks for subscription create/update, token create, invitation send/accept/reject, message send, task assign/unassign, and meeting create/update.
- [ ] Run lint and build after each phase, not only at the end.
- [ ] Add or update targeted frontend tests for the shared response parser and the most fragile flows from Phases 2 through 5.

Exit criteria:

- Every currently idempotent client route is intentionally covered.
- No non-idempotent route is getting a pointless global `Idempotency-Key` header.
- Release verification covers both contract correctness and duplicate-submit safety.

## Recommended Implementation Order Inside Each Phase

For each phase, use the same sequence:

1. Update shared parser or service code first.
2. Fix the smallest affected store or component slice.
3. Validate that slice immediately.
4. Move to the next neighboring consumer only after the first one is stable.

## Suggested Verification Checklist Per Phase

- Run the narrowest available frontend lint or build check after each phase.
- Smoke test every endpoint group touched in that phase.
- Confirm both success and validation-error payloads in the browser network tab.
- Confirm no screen is still branching on removed keys like `status`, `success`, `projectsCount`, `subscription`, `error_type`, or top-level resource names.

## Final Note On Idempotency

Based on the current codebase, no new broad frontend idempotency implementation is required before starting these phases.
The existing helper already covers the routes that currently require it.
The correct work is to preserve that coverage, document the rule, and only extend it when the backend adds idempotent protection to new client-initiated routes.
