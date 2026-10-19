# Frontend Post-Launch Roadmap & Enhancements

Updated: 2026-10-19

This document outlines **non-blocking enhancements, performance optimizations, and code hardening tasks** that can be safely scheduled and implemented in fast-follow sprints after the production release.

---

## Overview

The items listed here do **not** block shipping because the core application functionality, authentication boundaries, and data integrity are already secured by the pre-production phases. These tasks focus on improving user experience (initial page load times), architectural cleanliness, and long-term maintainability.

---

## Phase 4: Route Code Splitting & Performance Optimization (P1 — Post-Launch)

**Goal:** Reduce initial bundle download size and improve Time-to-Interactive (TTI) for first-time visitors by lazy-loading heavy route components.

### Current State

`resources/js/router.js` imports all 15+ views using static top-level ES module imports (e.g. `import ProjectPage from './components/Project/ProjectPage.vue'`). This bundles the entire application into a single JavaScript payload, forcing users to download code for views they may not visit.

### Tasks

1. **Convert Route Definitions to Dynamic Imports**
   - **Target File:** `resources/js/router.js`
   - **Action:** Replace static imports with dynamic `() => import('./components/...')` functions.
   - **Priority Targets (Heaviest Views):**
     - `ProjectPage.vue` (Complex layout, modals, task trees)
     - `Subscription.vue` (Paddle/pricing components)
     - Admin views (`AdminPanel`, `ProjectPanel`, `TaskPanel`, `UserPanel`)
     - `Activities.vue`
     - `ProfilePage.vue`

### Review & Verification Gate

- [ ] Run `npm run build` and inspect the generated chunks in `public/build/assets/`.
- [ ] Verify that separate `.js` chunks are created for lazy routes and loaded dynamically on navigation.
- [ ] Ensure route transitions are smooth without flashing unstyled content or navigation errors.

---

## Phase 5: Architecture & Codebase Hardening (P2 — Post-Launch)

**Goal:** Improve maintainability, decrease coupling between components, and standardize API interactions.

### Tasks

1. **Centralize Domain API Transport in Dedicated Services**
   - **Current:** Components frequently execute direct `axios.get()` or `axios.patch()` calls with ad-hoc URL construction.
   - **Action:** Move HTTP calls into dedicated service classes (e.g., `resources/js/services/ProjectService.js`, `resources/js/services/TaskService.js`) matching existing patterns like `IdempotencyRequestService.js`.
2. **Decouple Event Bus and Global Mixins**
   - **Current:** Heavy usage of `VueBus` and broad mixins (`alertNotice`, `conversation`, `errorHandling`) attached globally to all Vue instances.
   - **Action:** Replace event bus communication with Vuex actions or scoped component events. Replace direct DOM queries (e.g., `document.getElementById`) with Vue `$refs`.
3. **Expand Admin & Edge-Case Coverage**
   - **Action:** Add automated Jest/Cypress regression tests for lower-traffic admin panels, bulk member operations, and edge-case error recovery states.

---

## Tracking & Prioritization

| Enhancement                                   | Target Sprint        | Primary Benefit                                          |
| --------------------------------------------- | -------------------- | -------------------------------------------------------- |
| Route Code Splitting (Router Dynamic Imports) | Sprint 1 Post-Launch | Reduced initial bundle size, faster TTI                  |
| Domain API Service Centralization             | Sprint 2 Post-Launch | Clean architecture, centralized request/response mapping |
| Event Bus & DOM Refactoring                   | Sprint 2 Post-Launch | Fewer memory leaks, standard Vue component patterns      |
| Admin Panel Test Automation                   | Sprint 3 Post-Launch | Safe admin-side refactoring                              |
