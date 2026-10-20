# Frontend Pre-Production Remediation & Hardening Plan

Updated: 2026-10-20

This document outlines all **mandatory frontend fixes, performance enhancements, error monitoring, and verification steps** required prior to opening production traffic.

---

## Current Release Verdict

**In Progress — High Readiness.**

- **Completed:** Phase 0 (Transport/XSRF/TLS), Phase 1 (Vuex State Integrity & Storage Purge), Phase 2 (Runtime Crash Fixes & Contract Alignment), and Route Code Splitting (Dynamic Imports).
- **Remaining Before Launch:** Error Monitoring (Sentry & Vue errorHandler), E2E Golden Path Smoke Test, and Staging Runtime Verification Gate.

---

## Phase 0: Transport & Environment Foundation (P0 — Blocker) ✅ Completed

**Goal:** Establish correct HTTP/CSRF headers and secure WebSocket connectivity so all downstream communication with the Laravel backend works properly in production.

### Tasks

1. **Fix Axios XSRF Configuration** ✅ Completed
   - **Target File:** `resources/js/bootstrap.js`
   - **Action:** Set `xsrfCookieName = 'XSRF-TOKEN'` and `xsrfHeaderName = 'X-XSRF-TOKEN'`.
2. **Configure Dynamic WebSocket TLS** ✅ Completed
   - **Target File:** `resources/js/bootstrap.js`
   - **Action:** Set `forceTLS: import.meta.env.PROD`.

### Review & Verification Gate

- [x] Run `npm run lint` and verify no syntax or module import errors.
- [ ] Inspect network requests in browser to confirm `X-XSRF-TOKEN` header is sent with state-changing requests.
- [ ] Verify Echo connects without SSL/TLS downgrade errors on an HTTPS origin.

---

## Phase 1: State Integrity & Storage Hygiene (P0 — Blocker) ✅ Completed

**Goal:** Prevent form state corruption and cross-account data leakage in browser persistence.

### Tasks

1. **Fix Task Form Vuex Mutation** ✅ Completed
   - **Target File:** `resources/js/store/SingleTask`
   - **Action:** Changed `setForm` to `state.form = form;` so `state.task` remains intact.
2. **Restrict Vuex Persistence Allowlist & Clear on Logout** ✅ Completed
   - **Target Files:** `resources/js/store/index.js` & `resources/js/store/currentUser`
   - **Action:** Disabled persistence with `paths: []`. Added `localStorage.removeItem('vuex')` across logout, user deletion, and bootstrap failures.

### Review & Verification Gate

- [ ] Test task modal: open task, edit fields, click Cancel, reopen task. Confirm original task data is not corrupted.
- [ ] Test account switching: log in as User A, browse projects/tasks, log out. Verify User A data is purged from `localStorage` and does not appear when logging in as User B.

---

## Phase 2: Runtime Crash Fixes & Component Contract Alignment (P0 / P1 — Blocker) ✅ Completed

**Goal:** Eliminate undeclared runtime global dependencies that cause white-screen crashes, and ensure client mutation requests follow backend contracts.

### Tasks

1. **Declare SweetAlert2 & Chart.js Imports** ✅ Completed
   - **Target Files:** `Register.vue`, `ResetPassword.vue`, `Dashboard/ProjectChart.vue`
   - **Action:** Added explicit imports for `Swal` and `Chart.js`.
2. **Audit Concurrency Version on Task / Project Updates** ✅ Completed
   - **Target Files:** `TopArea.vue`, `Features.vue`
   - **Action:** Verified `version` is sent in PATCH payloads, local store updates on 200, and 409 `edit_conflict` triggers prompt.
3. **Refactor `alertNotice.performAction` Response Handling** ✅ Completed
   - **Target File:** `resources/js/mixins/alertNotice.js`
   - **Action:** Used `getResponseMessage(response)` from `apiResponse.js` and allowed optional redirect target.

### Review & Verification Gate

- [x] Run `npm run lint` — 0 errors.
- [x] View Dashboard chart; verify Chart renders without runtime exception.
- [ ] Trigger a concurrent task/project edit to verify 409 error handling toast appears.

---

## Phase 3: Route Code Splitting & Performance Optimization (P1) ✅ Completed

**Goal:** Reduce initial bundle size and improve Time-to-Interactive (TTI) by lazy-loading heavy route components.

### Tasks

1. **Dynamic Import for Route Components** ✅ Completed
   - **Target File:** `resources/js/router.js`
   - **Action:** Converted all 20 route definitions to dynamic imports (`() => import('./components/...')`).
   - **Verified Chunks:** `ProjectPage` (3.5MB), `Dashboard` (236KB), `ProfilePage` (97KB), `Subscription` (10KB), `Login`/`Register` (5KB).

### Review & Verification Gate

- [x] Run `npm run build` and inspect the generated chunks in `public/build/assets/`.
- [x] Verify separate chunk files are created for lazy-loaded routes and load on-demand when navigating.

---

## Phase 4: Error Observability & Safety Boundaries (P0 / P1 — Pre-Launch)

**Goal:** Provide instant visibility into frontend JavaScript exceptions in production so issues are caught immediately without relying on consumer bug reports, backed by automated end-to-end smoke verification.

### Tasks

1. **Frontend Sentry Integration (`@sentry/vue`)**
   - **Target Files:** `package.json`, `resources/js/app.js`
   - **Action:** Install `@sentry/vue` and initialize in `resources/js/app.js` conditioned on `import.meta.env.PROD` or `VITE_SENTRY_DSN`:
     - Bind `Vue` instance and `router` for automatic route tracking and transaction tracing.
     - Capture breadcrumbs (user clicks, route changes, Axios network failures).
     - Set an appropriate production sample rate (e.g. `tracesSampleRate: 0.2`).
2. **Global Vue & Window Error Handlers (Anti-White-Screen Boundary)**
   - **Target File:** `resources/js/app.js`
   - **Action:** Add `Vue.config.errorHandler` and `window.addEventListener('unhandledrejection')`:
     - Capture unhandled errors and report to Sentry with component context.
     - Provide a non-intrusive toast notification (`$vToastify.error(...)`) advising the user to refresh rather than leaving them on a frozen blank screen.
3. **Automated Playwright "Golden Path" E2E Smoke Tests**
   - **Target Files:** `playwright.config.js`, `tests/e2e/auth.spec.js`, `tests/e2e/projects-tasks.spec.js`, `tests/e2e/error-handling.spec.js`
   - **Action:** Install `@playwright/test` and create 3 fast, targeted headless test specs covering the 4 vital product loops:
     - [ ] **Workflow 1: Authentication (`tests/e2e/auth.spec.js`)**
       - Login with valid credentials → asserts redirect to `/dashboard` and session cookie stored.
       - Route guards redirect unauthenticated guests to `/login`.
       - Logout purges local storage/session and redirects to `/login`.
     - [ ] **Workflow 2: Projects & Tasks (`tests/e2e/projects-tasks.spec.js`)**
       - Create project → verifies project card renders on Dashboard.
       - Open task modal → update title → verifies optimistic update and version increment.
     - [ ] **Workflow 3: Authorization & Tenant Isolation (`tests/e2e/projects-tasks.spec.js`)**
       - User B attempts direct URL navigation to User A’s private project (`/projects/{user-a-slug}`) → asserts redirected or 403 error displayed, preventing cross-tenant data leakage.
     - [ ] **Workflow 4: Critical Failure States & Anti-White-Screen (`tests/e2e/error-handling.spec.js`)**
       - Intercept API mutation with simulated 409 conflict or network drop → asserts toast notification appears without crashing the UI or blanking the page.
   - **Deferred for Manual Staging Check:**
     - Team invitation emails (verified manually in staging to avoid mailhog fixture overhead).
     - Zoom Meeting SDK canvas & Paddle checkout popups (verified manually in staging sandbox; automated on backend with PHPUnit mocks).
   - **CI / Local Command:** Add npm script `"test:e2e": "playwright test tests/e2e"` to `package.json`.

### Review & Verification Gate

- [ ] Verify build passes with Sentry initialized and tree-shaken in production.
- [ ] Trigger a test error on staging; confirm event arrives in Sentry dashboard with full stack trace and breadcrumbs.
- [ ] Execute `npm run test:e2e` against staging and confirm all 3 spec suites pass.

---

## Phase 5: Staging Smoke Verification & Final Production Gate (P1 — Mandatory Gate)

**Goal:** Execute runtime end-to-end smoke tests against a production-like staging environment to generate definitive release evidence before opening production traffic.

### Mandatory Pre-Release Verification Checklist

- [x] Run `npm test`, `npm run lint`, and `npm run build`; ensure 0 failures.
- [ ] **Auth Flow:** Smoke-test Registration, Login, Logout, Forgot Password, Reset Password, and Email Verification.
- [ ] **2FA Flow:** Verify challenge prompt, valid code entry, and session expiry (wait > 5 mins on `/2fa/code` and verify redirect to `/login`).
- [ ] **Real-time (Pusher/Echo):** Verify over HTTPS that task updates and meeting notifications arrive without connection drops (`forceTLS: true`).
- [ ] **Projects & Tasks:** CRUD operations, stage transitions, task assignment, note updates, and 409 conflict handling.
- [ ] **Meetings & Subscriptions:** Create meeting with idempotency, check plan limits modal, verify token handling.
- [ ] **Storage Audit:** Inspect DevTools Application tab after logout to verify no sensitive data remains.
- [ ] **Console Audit:** Confirm zero unhandled promise rejections, 404 assets, or CORS/CSRF errors.

---

## Phase 6: Post-Launch Fast-Follow — Architecture & Hardening (P2)

**Goal:** Improve maintainability, decrease coupling between components, and standardize API interactions in post-release sprints.

### Tasks

1. **Centralize Domain API Transport in Dedicated Services**
   - **Action:** Move HTTP calls from components into dedicated service classes (e.g., `resources/js/services/ProjectService.js`, `resources/js/services/TaskService.js`) matching existing patterns like `IdempotencyRequestService.js`.
2. **Decouple Event Bus and Global Mixins**
   - **Action:** Replace event bus communication with Vuex actions or scoped component events. Replace direct DOM queries (`document.getElementById`) with Vue `$refs`.
3. **Admin & Edge-Case Test Coverage**
   - **Action:** Add automated tests for lower-traffic admin panels, bulk member operations, and edge-case error recovery states.

---

## Implementation History

- **2026-09-30 Review:** Initial static inspection comparing Vue call sites with Laravel routes, request validation, and API resources.
- **2026-10-19 Review:** Source code audit confirmed that CSRF configuration, `SingleTask` mutation, Vuex persistence, globals, and WebSocket TLS required concrete code remediations.
- **2026-10-19 Implementation:** Phase 0 code changes completed - Axios XSRF configuration and dynamic WebSocket TLS implemented. Lint verification passed.
- **2026-10-19 Implementation:** Phase 2 Task 1 & Task 3 completed - Explicit imports added for SweetAlert2, Chart.js, and alertNotice response handling refactored.
- **2026-10-20 Implementation:** Phase 1 completed - Fixed SingleTask `setForm` mutation (`state.form = form`), disabled Vuex persistence, added `localStorage.removeItem('vuex')` on logout.
- **2026-10-20 Implementation:** Route code splitting completed - All 20 routes dynamically imported; bundle chunked into separate assets.
