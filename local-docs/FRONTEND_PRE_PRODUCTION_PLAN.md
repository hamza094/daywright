# Frontend Pre-Production Remediation Plan

Updated: 2026-10-19

This document outlines all **mandatory, launch-blocking frontend fixes and verification steps** required prior to opening production traffic. All phases must be implemented and verified in sequential order.

---

## Current Release Verdict

**Not ready to ship yet.** Static review (2026-09-30) and live code inspection (2026-10-19) identified launch-blocking frontend defects across HTTP transport, real-time connectivity, state mutations, and store persistence. Phases 0 through 3 must be executed in order, concluding with browser verification in staging.

---

## Phase 0: Transport & Environment Foundation (P0 — Blocker)

**Goal:** Establish correct HTTP/CSRF headers and secure WebSocket connectivity so all downstream communication with the Laravel backend works properly in production.

### Tasks

1. **Fix Axios XSRF Configuration** ✅ Completed
   - **Target File:** `resources/js/bootstrap.js`
   - **Current:** `xsrfCookieName = 'XSRF-TOKEN'` and `xsrfHeaderName = 'X-XSRF-TOKEN'`
   - **Action:** Set `xsrfCookieName = 'XSRF-TOKEN'` and `xsrfHeaderName = 'X-XSRF-TOKEN'` (aligning with Laravel Sanctum defaults).
2. **Configure Dynamic WebSocket TLS** ✅ Completed
   - **Target File:** `resources/js/bootstrap.js`
   - **Current:** `forceTLS: import.meta.env.PROD` (dynamic based on environment)
   - **Action:** Set `forceTLS: import.meta.env.PROD` (or drive via Vite environment variable).

### Review & Verification Gate

- [x] Run `npm run lint` and verify no syntax or module import errors.
- [ ] Inspect network requests in browser to confirm `X-XSRF-TOKEN` header is sent with state-changing requests.
- [ ] Verify Echo connects without SSL/TLS downgrade errors on an HTTPS origin.

---

## Phase 1: State Integrity & Storage Hygiene (P0 — Blocker)

**Goal:** Prevent form state corruption and cross-account data leakage in browser persistence.

### Tasks

1. **Fix Task Form Vuex Mutation**
   - **Target File:** `resources/js/store/SingleTask`
   - **Current:** `setForm(state, form) { state.task = form; }` overwrites the loaded task entity.
   - **Action:** Change to `state.form = form;` so `state.task` remains intact.
2. **Restrict Vuex Persistence Allowlist & Clear on Logout**
   - **Target Files:** `resources/js/store/index.js` & `resources/js/store/currentUser`
   - **Current:** `createPersistedState()` persists the entire Vuex store without paths restriction.
   - **Action:** Define an explicit safe allowlist (e.g., non-sensitive UI preferences) or remove persistent domain state. Ensure user logout clears persisted domain keys from `localStorage`.

### Review & Verification Gate

- [ ] Test task modal: open task, edit fields, click Cancel, reopen task. Confirm original task data is not corrupted.
- [ ] Test account switching: log in as User A, browse projects/tasks, log out. Verify User A data is purged from `localStorage` and does not appear when logging in as User B.

---

## Phase 2: Runtime Crash Fixes & Component Contract Alignment (P0 / P1 — Blocker)

**Goal:** Eliminate undeclared runtime global dependencies that cause white-screen crashes, and ensure client mutation requests follow backend contracts.

### Tasks

1. **Declare SweetAlert2 & Chart.js Imports** ✅ Completed
   - **Target Files:**
     - `resources/js/components/Authentication/Register.vue`
     - `resources/js/components/Authentication/ResetPassword.vue`
     - `resources/js/components/Dashboard/ProjectChart.vue`
   - **Action:** Import SweetAlert2 (`import Swal from 'sweetalert2'`) and Chart.js explicitly instead of relying on undeclared `swal.fire` or `new Chart` globals.
2. **Audit Concurrency Version on Task / Project Updates** ✅ Already Implemented
   - **Target Files:**
     - `resources/js/components/Project/Panel/Modal/TopArea.vue`
     - `resources/js/components/Project/Panel/Features.vue`
   - **Action:** Confirm that `version` is sent in PATCH payloads, local store version updates on 200, and 409 `edit_conflict` triggers a user-friendly refresh/retry prompt.
3. **Refactor `alertNotice.performAction` Response Handling** ✅ Completed
   - **Target File:** `resources/js/mixins/alertNotice.js`
   - **Current:** Reads `response.data.message` directly and forces redirect to `/dashboard`.
   - **Action:** Use `getResponseMessage(response)` from `apiResponse.js` and allow optional/parameterized redirect targets.

### Review & Verification Gate

- [x] Test user registration and password reset; confirm SweetAlert modal renders without console error.
- [x] View Dashboard chart; verify Chart renders without `Chart is not defined` runtime exception.
- [ ] Trigger a concurrent task/project edit to verify 409 error handling toast appears.

---

## Phase 3: Staging Smoke Verification & Production Gate (P1 — Mandatory Gate)

**Goal:** Execute runtime end-to-end smoke tests against a production-like staging environment to generate definitive release evidence.

### Mandatory Pre-Release Verification Checklist

- [ ] Run `npm test`, `npm run lint`, and `npm run build`; ensure 0 failures.
- [ ] **Auth Flow:** Smoke-test Registration, Login, Logout, Forgot Password, Reset Password, and Email Verification.
- [ ] **2FA Flow:** Verify challenge prompt, valid code entry, and session expiry (wait > 5 mins on `/2fa/code` and verify redirect to `/login`).
- [ ] **Real-time (Pusher/Echo):** Verify over HTTPS that task updates and meeting notifications arrive without connection drops (`forceTLS: true`).
- [ ] **Projects & Tasks:** CRUD operations, stage transitions, task assignment, note updates, and 409 conflict handling.
- [ ] **Meetings & Subscriptions:** Create meeting with idempotency, check plan limits modal, verify token handling.
- [ ] **Storage Audit:** Inspect DevTools Application tab after logout to verify no sensitive data remains.
- [ ] **Console Audit:** Confirm zero unhandled promise rejections, 404 assets, or CORS/CSRF errors.

---

## Evidence and Scope

- **2026-09-30 Review:** Initial static inspection comparing Vue call sites with Laravel routes, request validation, and API resources.
- **2026-10-19 Review:** Source code audit confirmed that CSRF configuration, `SingleTask` mutation, Vuex persistence, globals, and WebSocket TLS required concrete code remediations. 2FA session expiry subtraction was analyzed and verified safe (implicit numeric conversion), retaining staging verification in Phase 3.
- **2026-10-19 Implementation:** Phase 0 code changes completed - Axios XSRF configuration and dynamic WebSocket TLS implemented. Lint verification passed. Manual browser verification (network headers and Echo connection) pending in staging/production.
- **2026-10-19 Implementation:** Phase 2 Task 1 completed - Explicit imports added for SweetAlert2 in Register.vue and ResetPassword.vue, and Chart.js in ProjectChart.vue. Lint verification passed.
- **2026-10-19 Audit:** Phase 2 Task 2 verified - Concurrency version handling already properly implemented in TopArea.vue and Features.vue. Version sent in PATCH requests, local store updated on success, and 409 errors handled with user-friendly prompts.
- **2026-10-19 Implementation:** Phase 2 Task 3 completed - Refactored alertNotice.performAction to use getResponseMessage helper and accept optional redirectPath parameter. Also fixed ProfilePage.vue (2 instances) to use getResponseMessage instead of direct response.data.message access. Existing usages maintain backward compatibility.
