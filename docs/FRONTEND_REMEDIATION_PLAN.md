# Frontend Production Readiness Plan

Updated: 2026-05-24

This plan is based on a fresh review of the current frontend codebase, not only the earlier audit snapshot.
It is organized in phases so you can work through it one phase at a time.

## Current Review Summary

### What improved since the earlier audit

- Response normalization is moving in the right direction through utility helpers such as auth, task, dashboard, and notification response parsers.
- Some low-level frontend tests now exist under `resources/js/utils/` and `resources/js/services/`.
- The production build currently completes successfully.
- Some store and API parsing code is cleaner than before, especially in `currentUser`, `task`, and `notifications`.

### What is still holding the frontend back

- HTTP transport and CSRF/session configuration are still fragile.
- The app still persists the entire Vuex store to browser storage.
- Several important defects are runtime-only and are not caught by the current build.
- Routing is still eager-loaded.
- Large smart components still mix transport, state mutation, view logic, and side effects.
- The current test work is not yet wired into a full frontend test workflow or release gate.
- Event bus and global mixin coupling are still present.

### Important note from this review

`npm run build` passes today.
That means the highest-risk remaining issues are not simple compile-time failures.
They are runtime correctness, state integrity, and maintainability problems that can still hurt production behavior.

## Priority Overview

| Phase | Focus                                         | Priority | Ship status                            |
| ----- | --------------------------------------------- | -------- | -------------------------------------- |
| 1     | Runtime transport and auth correctness        | Critical | Must finish before production          |
| 2     | State integrity and persistence hardening     | Critical | Must finish before production          |
| 3     | Runtime safety nets and release validation    | Critical | Must finish before production          |
| 4     | API layer consolidation                       | High     | Strongly recommended before production |
| 5     | Component decomposition and route performance | High     | Strongly recommended before production |
| 6     | Hidden coupling cleanup and frontend polish   | Medium   | Can follow after launch                |

## Must Fix Before Production

These phases are production gates.
Do not ship until they are complete.

## Phase 1 - Runtime Transport and Auth Correctness

Priority: Critical  
Ship status: Must finish before production

### Why this phase is first

- Authentication and session correctness are foundational.
- Current issues here are runtime issues, not build issues.
- If this layer is unreliable, every later frontend improvement is built on unstable behavior.

### Verified current issues

- `resources/js/bootstrap.js` still uses one global axios client for everything.
- `xsrfCookieName` and `xsrfHeaderName` are still configured as booleans instead of header names.
- The auth store still mixes session-oriented requests with the API-base setup.
- `Register.vue` and `ResetPassword.vue` still call `swal.fire(...)` without explicit imports.
- `ProjectChart.vue` still calls `new Chart(...)` without importing `Chart`.

### Primary files

- `resources/js/bootstrap.js`
- `resources/js/app.js`
- `resources/js/store/currentUser`
- `resources/js/components/Authentication/Login.vue`
- `resources/js/components/Authentication/Register.vue`
- `resources/js/components/Authentication/ResetPassword.vue`
- `resources/js/components/Authentication/TwoFACode.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/Dashboard/ProjectChart.vue`

### Step-by-step work

- [ ] Split frontend HTTP usage into explicit clients instead of relying on one global axios configuration.
- [ ] Keep one client for `/api/v1` traffic.
- [ ] Keep one session-aware client or root-scope client for `/sanctum/csrf-cookie` and `/api/v1/session/*` flows.
- [ ] Fix XSRF config values in `bootstrap.js` so they use real cookie and header names.
- [ ] Move shared interceptors into reusable setup code instead of attaching everything directly to the global axios instance.
- [ ] Normalize login, logout, session bootstrap, and 2FA requests so their URLs and transport layer are intentionally scoped.
- [ ] Replace direct `swal.fire(...)` usage with imported `Swal` or route those flows through the shared alert helper.
- [ ] Fix `ProjectChart.vue` so chart creation uses one clear implementation path with explicit imports.
- [ ] Verify password reset, login, logout, 2FA login confirm, and dashboard chart behavior manually after transport cleanup.

### Exit criteria

- Auth and session flows work without relying on accidental axios defaults.
- CSRF behavior is explicitly configured and stable.
- Auth-related screens do not depend on undeclared global browser variables.
- Dashboard chart rendering no longer depends on undeclared globals.

## Phase 2 - State Integrity and Persistence Hardening

Priority: Critical  
Ship status: Must finish before production

### Why this phase is second

- The current store is functional but still too loose for production-grade predictability.
- Whole-store persistence and direct component-driven state mutation will create stale data and debugging problems.
- This phase reduces hidden state corruption before broader refactors begin.

### Verified current issues

- `createPersistedState()` still persists the entire Vuex store.
- `SingleTask.setForm` still writes into `state.task` instead of `state.form`.
- `notifications.js` still is not namespaced while most other modules are.
- `ProjectPage.vue` still mutates mapped state directly for realtime activity updates.
- The router still reads 2FA state from localStorage directly.

### Primary files

- `resources/js/store/index.js`
- `resources/js/store/currentUser`
- `resources/js/store/project`
- `resources/js/store/task`
- `resources/js/store/SingleTask`
- `resources/js/store/notifications.js`
- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Notification.vue`
- `resources/js/components/UserNotification.vue`
- `resources/js/router.js`

### Step-by-step work

- [ ] Remove blanket Vuex persistence or reduce it to a strict allowlist of safe UI-only values.
- [ ] Ensure logout clears any persisted frontend state that should not survive a session.
- [ ] Fix `SingleTask.setForm` to write to `state.form`.
- [ ] Standardize all store modules to use `namespaced: true` unless there is a very strong reason not to.
- [ ] Add getters/selectors for frequently accessed state instead of depending on direct `$store.state` lookups everywhere.
- [ ] Replace direct mutations in `ProjectPage.vue` with explicit `project` store mutations or actions.
- [ ] Audit all components for direct mutation of mapped store objects and remove those patterns.
- [ ] Normalize notification actions, commits, and selectors under a consistent store contract.
- [ ] Re-evaluate whether the 2FA pending marker belongs in localStorage or should become a more controlled session-state mechanism.

### Exit criteria

- Sensitive or stale state is no longer broadly persisted.
- Store writes happen through mutations and actions, not ad-hoc component mutation.
- Store module contracts are predictable and consistent.
- Realtime project and notification flows remain correct after the cleanup.

## Phase 3 - Runtime Safety Nets and Release Validation

Priority: Critical  
Ship status: Must finish before production

### Why this phase is still pre-production

- The codebase now has some test files, but they are not enough to protect critical flows.
- There is still no complete frontend release gate tied to runtime-sensitive behavior.
- Current high-risk defects can pass the build and still fail in production.

### Verified current issues

- `package.json` still has no frontend test script.
- There is no obvious wired test runner in the frontend toolchain.
- Existing test files are mostly utility/service-focused and do not yet protect auth, router, or key UI flows.
- Build success today does not catch runtime-only defects like undeclared globals.

### Primary files

- `package.json`
- frontend test config files to be added
- critical stores and auth/router components

### Step-by-step work

- [ ] Choose and install a Vue 2-compatible frontend test runner and component test stack.
- [ ] Add a `test` script and any supporting watch or CI variants to `package.json`.
- [ ] Keep the existing utility tests, but wire them into the official frontend test workflow.
- [ ] Add tests for auth response parsing, session bootstrap, login flow branching, and 2FA handling.
- [ ] Add tests for router guard behavior.
- [ ] Add tests for project activity mutations and notification store behavior.
- [ ] Add at least one runtime safety smoke test around dashboard chart rendering.
- [ ] Add release-gate commands for lint, tests, and production build.
- [ ] Document a minimal manual smoke checklist for login, dashboard, subscriptions, project detail, and notifications.

### Exit criteria

- Frontend tests run through a documented command in `package.json`.
- Critical flows have automated coverage, not only utility helper tests.
- Lint, tests, and build are part of the release gate.

## Strongly Recommended Before Production

These are not as immediately blocking as the first three phases, but shipping without them will keep the frontend expensive to maintain and slower to evolve.

## Phase 4 - API Layer Consolidation

Priority: High  
Ship status: Strongly recommended before production

### Why this phase matters

- Some response parsing has improved, but API orchestration is still spread across many components.
- The codebase will remain hard to change safely until transport is centralized by domain.

### Verified current issues

- Components still call axios directly for many domain operations.
- UI components still own too much payload shaping, success handling, and error branching.
- Response parser utilities exist, but the service layer is still inconsistent across domains.

### Primary files

- `resources/js/services/`
- `resources/js/store/`
- `resources/js/components/Dashboard/Dashboard.vue`
- `resources/js/components/Subscription.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/ProjectForm.vue`
- `resources/js/components/Admin/Users.vue`
- `resources/js/components/Notification.vue`

### Step-by-step work

- [ ] Define a consistent service-layer pattern and document it.
- [ ] Use existing parser utilities as part of service-layer response normalization instead of leaving parsing inside components.
- [ ] Create or complete service modules for auth, dashboard, subscriptions, notifications, projects, tasks, and 2FA.
- [ ] Move endpoint paths and payload formatting into those services or into the store actions that use them.
- [ ] Keep page components focused on orchestration and view state only.
- [ ] Standardize async state handling for loading, success, empty, and error states.
- [ ] Remove UI behavior that depends on backend message text where structured response data should be used instead.

### Exit criteria

- Domain API behavior is mostly centralized.
- Endpoint changes no longer require editing many unrelated components.
- Error and loading behavior are more consistent across the app.

## Phase 5 - Component Decomposition and Route Performance

Priority: High  
Ship status: Strongly recommended before production

### Why this phase matters

- The current app still has several high-complexity components.
- Routing is still eager-loaded, which is avoidable in a Vue 2 SPA with this size.
- This phase improves scalability and lowers regression risk for future work.

### Verified current issues

- `router.js` still imports every route component eagerly.
- `ProjectPage.vue` still mixes realtime, store coordination, view state, and transport logic.
- `Subscription.vue` and `TwoFactorAuth.vue` remain broad smart components.
- Reusable form and layout patterns exist, but they are not yet applied consistently.

### Primary files

- `resources/js/router.js`
- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Subscription.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/Dashboard/Dashboard.vue`
- `resources/js/components/ProjectForm.vue`

### Step-by-step work

- [ ] Convert route components to lazy-loaded imports grouped by feature area.
- [ ] Split `ProjectPage.vue` into a page container and focused child sections.
- [ ] Split `Subscription.vue` into plan overview, billing actions, payment modal, receipts, and usage sections.
- [ ] Split `TwoFactorAuth.vue` into status, recovery-code management, setup flow, and destructive actions.
- [ ] Move repeated permission and derived-state logic into helpers or selectors.
- [ ] Expand reusable form primitives where the same validation and field markup repeats.
- [ ] Standardize loading, empty, and error states across major views.

### Exit criteria

- Route loading is no longer fully eager.
- Large smart components have clearer ownership boundaries.
- Presentation components receive props and emit events instead of owning domain logic.

## Can Follow After Launch

## Phase 6 - Hidden Coupling Cleanup and Frontend Polish

Priority: Medium  
Ship status: Can follow after launch

### Why this phase is later

- These issues matter, but they are less urgent than auth, state, and release safety.
- They should be cleaned up once the production gates are closed.

### Verified current issues

- Global mixins are still attached app-wide.
- The event bus is still used across meetings, project panels, and modal coordination.
- Direct browser globals and DOM lookups still exist in router and component logic.
- There is still some dead or low-confidence bootstrap code, such as the `ProfilePge.vue` registration path in `app.js`.

### Primary files

- `resources/js/app.js`
- `resources/js/mixins/alertNotice.js`
- `resources/js/mixins/conversation.js`
- project, meeting, notification, and modal components using `$bus`

### Step-by-step work

- [ ] Replace global mixins with explicit imports where practical.
- [ ] Reduce `$bus` usage in favor of parent-child events, store actions, or feature-level controllers.
- [ ] Replace direct DOM queries with refs or dedicated utilities.
- [ ] Remove dead registrations, typos, stale code paths, and low-value bootstrap coupling.
- [ ] Review remaining `console.*` usage and keep debug logging behind development guards.

### Exit criteria

- Cross-component behavior is easier to trace.
- The frontend has fewer hidden globals and less implicit coupling.
- Bootstrap and shared infrastructure become easier to reason about.

## Recommended Working Order

Work through the plan in this order:

1. Finish Phase 1 completely.
2. Finish Phase 2 completely.
3. Add the release safety net in Phase 3.
4. Then move into the strongly recommended architecture work in Phase 4 and Phase 5.
5. Leave Phase 6 for cleanup once the app is already stable.

## Validation Checklist For Every Phase

- [ ] `npm run lint`
- [ ] `npm run build`
- [ ] Run the smallest relevant frontend test subset
- [ ] Manual smoke test the touched user flow
- [ ] Confirm there are no new console errors in that flow

## Minimum Shipping Checklist

Do not ship until all of these are true:

- [ ] Phase 1 is complete
- [ ] Phase 2 is complete
- [ ] Phase 3 is complete
- [ ] Login, logout, password reset, 2FA, dashboard, subscriptions, project detail, and notifications are manually smoke-tested
- [ ] Frontend lint, tests, and production build are all part of the release gate

## Notes From This Review

- No uncommitted frontend file changes were detected when this plan was updated.
- The current build succeeds, so do not mistake build success for production readiness.
- The highest-risk remaining work is now runtime correctness and frontend architecture discipline.
