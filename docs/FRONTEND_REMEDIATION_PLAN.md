# Frontend Production Remediation Plan

Created: 2026-05-01

This plan converts the frontend architecture audit into a phased execution roadmap.
Work through the phases in order. Do not start broad refactors before the earlier stability phases are complete.

## Production Gate Summary

| Phase | Focus                                         | Priority | Production Gate                        |
| ----- | --------------------------------------------- | -------- | -------------------------------------- |
| 1     | Transport and runtime stabilization           | Critical | Must finish before production          |
| 2     | State integrity and persistence hardening     | Critical | Must finish before production          |
| 3     | Critical flow test coverage and release gates | Critical | Must finish before production          |
| 4     | API layer consolidation                       | High     | Strongly recommended before production |
| 5     | Component decomposition and route performance | High     | Strongly recommended before production |
| 6     | Hidden coupling cleanup and polish            | Medium   | Can follow after launch                |

## Phase 1 - Transport and Runtime Stabilization

Priority: Critical  
Production gate: Must finish before production

Why this phase comes first:

- It removes auth and session fragility.
- It fixes runtime hazards that can break the app even if the code compiles.
- It gives the rest of the refactor a stable HTTP foundation.

Main audit findings covered:

- Fragile CSRF and session axios configuration
- Runtime import and global dependency issues
- Dashboard chart runtime risk

Files likely involved:

- `resources/js/bootstrap.js`
- `resources/js/app.js`
- `resources/js/store/currentUser`
- `resources/js/components/Authentication/Login.vue`
- `resources/js/components/Authentication/Register.vue`
- `resources/js/components/Authentication/ResetPassword.vue`
- `resources/js/components/Authentication/TwoFACode.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/Dashboard/ProjectChart.vue`

Step-by-step tasks:

- [ ] Create dedicated HTTP clients instead of one global axios setup.
- [ ] Add an `apiClient` for `/api/v1` requests.
- [ ] Add a `sessionClient` or root client for `/sanctum/csrf-cookie` and `/api/v1/session/*` flows.
- [ ] Stop relying on base URL concatenation for cross-scope requests.
- [ ] Fix XSRF configuration in `bootstrap.js` so cookie and header names are valid strings, not booleans.
- [ ] Move shared interceptors into a reusable setup function so both clients can use consistent progress and error handling.
- [ ] Normalize auth and session calls in the auth store and auth components to use the correct client.
- [ ] Fix `ProjectChart.vue` so it uses Chart.js through one clear path only.
- [ ] Import `Swal` explicitly where `swal.fire(...)` is called, or replace those direct calls with the shared alert helper.
- [ ] Fix or remove the broken global component registration for `ProfilePge.vue` in `app.js`.
- [ ] Run manual smoke tests for login, logout, password reset, 2FA setup, 2FA login confirm, and dashboard chart rendering.

Exit criteria:

- Authentication works consistently with cookies and CSRF in local and production-like environments.
- No unresolved import errors remain in auth or dashboard entry flows.
- Dashboard chart renders without depending on an undeclared global.
- `npm run build` completes cleanly.

## Phase 2 - State Integrity and Persistence Hardening

Priority: Critical  
Production gate: Must finish before production

Why this phase comes second:

- It removes stale and unsafe client-side state behavior.
- It restores Vuex predictability before larger refactors start.
- It reduces the chance of hidden regressions in project, task, and notification flows.

Main audit findings covered:

- Whole-store persistence to localStorage
- Direct mutation of mapped Vuex state inside components
- Broken and inconsistent Vuex module contracts

Files likely involved:

- `resources/js/store/index.js`
- `resources/js/store/project`
- `resources/js/store/task`
- `resources/js/store/SingleTask`
- `resources/js/store/notifications.js`
- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Project/Panel/TaskDetailModal.vue`
- `resources/js/components/Notification.vue`
- `resources/js/components/UserNotification.vue`

Step-by-step tasks:

- [ ] Remove blanket `createPersistedState()` usage or restrict it to a strict whitelist of safe UI-only keys.
- [ ] Ensure persisted state is cleared on logout.
- [ ] Fix `SingleTask.setForm` so it writes to `state.form`, not `state.task`.
- [ ] Remove Vuex actions that call component-only helpers such as `this.handleErrorResponse(...)`.
- [ ] Standardize every Vuex module to use `namespaced: true`.
- [ ] Add missing getters for read-heavy state instead of direct `$store.state` access everywhere.
- [ ] Replace direct store-object mutation in `ProjectPage.vue` with explicit mutations or actions.
- [ ] Search for and remove any remaining `push`, `splice`, `unshift`, or property assignment against mapped store state from components.
- [ ] Normalize notification store access so components dispatch namespaced actions and commits consistently.
- [ ] Verify logout, project activity updates, archived task flows, task detail updates, and notifications after store cleanup.

Exit criteria:

- Sensitive or stale data is not persisted broadly in localStorage.
- Components no longer mutate Vuex state directly.
- Core store modules expose predictable contracts with namespaces, actions, mutations, and getters.
- Project and notification flows still work after refactoring.

## Phase 3 - Critical Flow Test Coverage and Release Gates

Priority: Critical  
Production gate: Must finish before production

Why this phase is still pre-production:

- The current frontend has effectively no test safety net.
- Earlier phases will touch auth, state, and routing, which are high-risk areas.
- Shipping without tests means every later frontend change will stay expensive and risky.

Main audit findings covered:

- No frontend unit or component tests
- Runtime hazards not being caught before release

Files likely involved:

- `package.json`
- frontend test config files to be added
- critical stores and critical page components

Step-by-step tasks:

- [ ] Choose and install a Vue 2-compatible frontend test stack.
- [ ] Add test support for Vuex stores, router guards, and Vue components.
- [ ] Add tests for `currentUser` bootstrap, login, logout, and 2FA flows.
- [ ] Add tests for router guards in `router.js`.
- [ ] Add tests for notification store behavior and project activity store mutations.
- [ ] Add a smoke test for `ProjectChart.vue` so chart regressions are caught.
- [ ] Add CI commands for lint, test, and build.
- [ ] Fail CI on unresolved imports and build errors.
- [ ] Define a release checklist for frontend verification before each deploy.

Exit criteria:

- Critical auth and state flows have automated coverage.
- Lint, test, and build are all part of the release gate.
- Runtime breakages like missing imports are caught before deployment.

## Phase 4 - API Layer Consolidation

Priority: High  
Production gate: Strongly recommended before production

Why this phase matters:

- API logic is currently scattered across UI components.
- Endpoint changes will remain expensive until transport is centralized.
- It is the main step needed to keep the codebase maintainable after launch.

Main audit findings covered:

- Raw axios calls scattered across components
- Tight coupling between views and backend response shapes
- Inconsistent error and loading behavior

Files likely involved:

- `resources/js/services/`
- `resources/js/store/`
- `resources/js/components/Dashboard/Dashboard.vue`
- `resources/js/components/Subscription.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/ProjectForm.vue`
- `resources/js/components/Admin/Users.vue`
- `resources/js/components/Notification.vue`

Step-by-step tasks:

- [ ] Establish a standard service pattern using `ProjectInsightsService.js` as the reference direction.
- [ ] Create service modules for auth, subscriptions, dashboard, notifications, projects, tasks, and 2FA.
- [ ] Move endpoint URLs, payload shaping, and response normalization into those services.
- [ ] Keep components responsible only for presentation, orchestration, and emitting UI events.
- [ ] Move API calls in page-level components either into Vuex actions or dedicated service calls wrapped by page containers.
- [ ] Standardize error mapping so UI code never branches on raw backend message text.
- [ ] Standardize async state shape for all major flows: `idle`, `loading`, `success`, `error`.
- [ ] Replace duplicated toast and progress handling with shared utilities.

Exit criteria:

- Most domain API traffic is no longer issued directly from view components.
- Endpoint and payload changes can be made in a single domain layer.
- Loading and error UX follow one consistent pattern.

## Phase 5 - Component Decomposition and Route Performance

Priority: High  
Production gate: Strongly recommended before production

Why this phase matters:

- Several major pages are too large and mix too many concerns.
- Large eager-loaded route bundles will slow initial load.
- This is the phase that turns the codebase from workable to scalable.

Main audit findings covered:

- God components
- Weak separation of container and presentational responsibilities
- Eager route loading
- Inconsistent form handling and reused UI primitives

Files likely involved:

- `resources/js/components/Project/ProjectPage.vue`
- `resources/js/components/Subscription.vue`
- `resources/js/components/Profile/TwoFactorAuth.vue`
- `resources/js/components/Dashboard/Dashboard.vue`
- `resources/js/components/ProjectForm.vue`
- `resources/js/router.js`

Step-by-step tasks:

- [ ] Split `ProjectPage.vue` into a thin container plus focused child sections for summary, activity, meeting, side panel, and realtime wiring.
- [ ] Split `Subscription.vue` into overview, billing actions, usage display, receipts, and payment modal pieces.
- [ ] Split `TwoFactorAuth.vue` into status display, recovery-code management, setup form, and modal actions.
- [ ] Move permission calculation and repeated derived state into selectors or helpers.
- [ ] Expand use of reusable form and field components beyond `Profile/Edit.vue`.
- [ ] Standardize empty, loading, and error states across pages.
- [ ] Convert route components in `router.js` to lazy-loaded imports.
- [ ] Group admin routes and heavy project routes into separate chunks.

Exit criteria:

- Large components are decomposed into maintainable units.
- Container components own data-fetching and orchestration; presentational components receive props and emit events.
- Initial route bundle size is reduced through code splitting.

## Phase 6 - Hidden Coupling Cleanup and Polish

Priority: Medium  
Production gate: Can follow after launch

Why this phase is later:

- It improves long-term maintainability more than immediate release stability.
- Earlier phases should first stop the real production blockers.

Main audit findings covered:

- Heavy reliance on global mixins
- Event bus coupling
- Direct browser global usage and DOM access
- Naming and cleanup issues

Files likely involved:

- `resources/js/app.js`
- `resources/js/mixins/alertNotice.js`
- `resources/js/mixins/conversation.js`
- meeting, project, and modal components using `$bus`

Step-by-step tasks:

- [ ] Remove app-wide global mixins where explicit imports are clearer and safer.
- [ ] Replace `$bus`-based cross-component flows with Vuex actions, parent-child events, or dedicated controllers.
- [ ] Replace direct `window`, `document`, and manual DOM lookup usage with isolated utilities or component refs.
- [ ] Remove dead code, typos, broken naming, and stale comments.
- [ ] Audit `console.*` usage and keep debug logging behind development guards only.
- [ ] Review whether any remaining large collections should be normalized instead of stored as nested raw API objects.

Exit criteria:

- Cross-component communication is explicit and traceable.
- Hidden global dependencies are reduced.
- The codebase is easier to reason about for future contributors.

## Recommended Execution Order Inside Each Phase

For every phase, use this order:

1. Stabilize the shared foundation first.
2. Refactor one domain at a time.
3. Validate immediately after each domain change.
4. Merge only when lint, build, and the relevant tests pass.

## Validation Checklist Per Phase

Repeat this checklist at the end of every phase:

- [ ] `npm run lint`
- [ ] `npm run build`
- [ ] Run the smallest relevant frontend test subset
- [ ] Manually smoke-test the changed flow in the browser
- [ ] Confirm there are no new console errors for that flow

## Minimum Pre-Production Stop Line

Do not ship before all of the following are true:

- [ ] Phase 1 is complete
- [ ] Phase 2 is complete
- [ ] Phase 3 is complete
- [ ] Authentication, 2FA, notifications, project detail, tasks, and subscriptions have passed manual smoke testing
- [ ] Frontend lint, test, and build are part of the release gate

## Suggested Working Strategy

- Finish and merge one phase at a time.
- Do not combine Phase 1 and Phase 5 work in the same branch.
- Keep Phase 1 through Phase 3 focused on stability, not visual redesign.
- Start the broader architecture cleanup only after the release blockers are closed.
