# Frontend Production Readiness Plan

Updated: 2026-09-30

This is the single active frontend plan. It consolidates the API/frontend alignment plan, Phase 1 endpoint matrix, frontend/backend contract remediation plan, and the earlier frontend readiness review. Completed alignment work is intentionally omitted; historical checkboxes are not release evidence.

## Current release verdict

**Not ready to ship yet.** Static review found launch-blocking frontend defects in task/project editing, form state, CSRF configuration, and undeclared runtime globals. The code audit did not exercise the browser against staging, so the release gate below still requires runtime evidence.

## P0 — Fix before production

| Finding                                                             | Current behavior                                                                                                                                                    | Required change                                                                                                                                                                                                                                                                                                          |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Task title update omits concurrency version                         | `resources/js/components/Project/Panel/Modal/TopArea.vue` PATCHes only `title`; `TaskUpdateRequest` requires `version`.                                             | Send `task.version`, update local task/version from the wrapped response, and handle 409 `edit_conflict` with a clear reload/reconcile path.                                                                                                                                                                             |
| Project notes update omits version and reads legacy response fields | `resources/js/components/Project/Panel/Features.vue` PATCHes only `notes`, then reads top-level `project` and `message`. `ProjectUpdateRequest` requires `version`. | Send current project version; parse the wrapped resource through the shared response utility; update project state/version; use local success text if the response has no message.                                                                                                                                       |
| Task form mutation targets the wrong state property                 | `resources/js/store/SingleTask` implements `setForm` as `state.task = form`, while the form lives in `state.form`.                                                  | Set `state.form`; verify opening, editing, canceling, and closing a task modal do not replace or corrupt the task record or next edit payload.                                                                                                                                                                           |
| Axios XSRF names are invalid                                        | `resources/js/bootstrap.js` sets `xsrfCookieName` and `xsrfHeaderName` to booleans.                                                                                 | Configure the actual cookie/header names used by the deployed Laravel/Sanctum setup and verify cookie acquisition, login, authenticated requests, logout, and CSRF rejection/recovery in a browser. Keep the client setup as small as possible; split clients only if a concrete cross-origin/base-URL need requires it. |
| Runtime globals are undeclared                                      | `Register.vue` and `ResetPassword.vue` call `swal.fire`; `Dashboard/ProjectChart.vue` calls `new Chart` without an explicit import.                                 | Import and use the installed SweetAlert2 and Chart.js APIs explicitly (or a verified app-owned wrapper), then exercise registration, reset, and chart rendering at runtime.                                                                                                                                              |
| Whole Vuex store is persisted                                       | `resources/js/store/index.js` applies `createPersistedState()` without a state allowlist.                                                                           | Stop persisting session/domain data, or restrict persistence to explicitly safe UI preferences. Clear relevant persisted state on logout and verify signing out/in as a different user cannot display the previous user's project, task, notification, or profile data.                                                  |

## P1 — Release verification (required before opening production traffic)

### API contract rules

- Wrapped resources: use `getObjectData(response)` or `getResponseData(response)`; paginated collections: use `getPaginatedData(response)`; message-only responses: use `getResponseMessage(response)`; failures: use `parseApiError(error)`.
- Do not add global response unwrapping or infer resource payloads from message-only responses.
- Project and task PATCH requests require the latest `version`; stale writes return 409 `edit_conflict`. Refresh the resource and let the user retry/reconcile.
- For pagination, send only validated canonical query values and follow returned `links.next`; preserve endpoint-specific compatibility such as meetings `request=previous`.
- Add an `Idempotency-Key` only through the existing dedicated helper for routes that currently use backend idempotency middleware. Keep database operation idempotency authoritative for subscription flows; do not add a global header interceptor.

### Critical endpoint matrix

Client paths below are relative to the configured API base URL unless marked as browser/session behavior. This is a compact production matrix, not an exhaustive route inventory.

| Flow                                     | Endpoint(s)                                                                                                                                                                 | Success shape / critical constraint                                                                                                                         |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Session/auth                             | `POST /session/login`, `POST /session/logout`, `GET /users/me`                                                                                                              | Login and current-user resources are wrapped; login may return `data.two_factor_state`; logout is message-only. Verify Sanctum cookie/CSRF behavior.        |
| Two-factor                               | `POST /twofactor/login-confirm`, `GET /twofactor/status`, `POST /twofactor/setup`, `POST /twofactor/confirm`, `POST /twofactor/recovery-codes`, `DELETE /twofactor/disable` | Wrapped resources; status route is `/status`; recovery codes use POST. Challenge completion and authenticated settings have different session requirements. |
| Email verification                       | `POST /email/verify/{user}`, `POST /email/resend`                                                                                                                           | Verify is wrapped; resend is message-only and has no `{user}` path segment. Check invalid/expired link and validation UX.                                   |
| Projects/tasks                           | `PATCH /projects/{project}`, `PATCH /projects/{project}/stage`, `PATCH /projects/{project}/tasks/{task}`                                                                    | Wrapped resources; project/task edits require `version`; test success and stale-version conflict behavior.                                                  |
| Task assignees                           | `POST /projects/{project}/tasks/{task}/assignees`, `DELETE /projects/{project}/tasks/{task}/assignees/{user}`                                                               | Assign is idempotent; removal is not. Frontend paths must remain REST-style.                                                                                |
| Meetings                                 | `POST /projects/{project}/meetings`, `PATCH /projects/{project}/meetings/{meeting}`                                                                                         | Wrapped resources; client mutations use dedicated idempotency requests. Include duplicate-submit and provider-error/retry smoke checks.                     |
| Collections                              | Projects, dashboard tasks/activities, invitations, notifications, admin lists                                                                                               | Native paginated responses are `{ data, meta, links }`; preserve validated filters/sort/page size when following pagination links.                          |
| Subscription/tokens/invitations/messages | See registered routes and `resources/js/services/idempotencyCoverage.test.js`                                                                                               | Match the current endpoint method, payload, response type, auth boundary, and backend idempotency middleware. Do not assume every mutation is idempotent.   |

### Pre-release checklist

- [ ] Fix every P0 item and add focused regression coverage for each corrected behavior.
- [ ] Run `npm test`, `npm run lint`, and `npm run build`; investigate failures rather than treating build success as runtime proof.
- [ ] Smoke-test registration, login, logout, password reset, email verification/resend, 2FA challenge and settings, dashboard/chart, project/task CRUD and edits, invitations, meetings, notifications, subscriptions, tokens, and admin access in production-like staging.
- [ ] For task/project edits, confirm success updates the local version; submit a stale version and confirm 409 recovery works without silently losing the user's changes.
- [ ] Verify no cross-account domain data survives logout/login; check persisted browser storage directly.
- [ ] Check browser console/network output for failed requests, unhandled promise rejections, cookie/CORS/CSRF issues, and broken asset URLs.
- [ ] Confirm production API base URL, HTTPS, cookie domain/SameSite/Secure settings, CSRF, CORS, websocket/Pusher configuration, and public asset build configuration.
- [ ] Record staging environment, build identifier, commands/results, smoke-test outcomes, and any open provider/infrastructure checks. Leave unavailable real-provider checks explicitly open.

## P2 — Can follow after launch

These are valuable maintenance/performance improvements but are not release blockers unless they reveal a concrete defect:

- Centralize more Axios calls in domain services and reduce transport/payload logic in components.
- Lazy-load route components and split large smart components such as `ProjectPage.vue`, `Subscription.vue`, and `TwoFactorAuth.vue`.
- Reduce event-bus/global-mixin coupling and replace direct DOM lookups with refs where practical.
- Expand endpoint-matrix detail for lower-traffic admin and edge-case endpoints after all production-critical routes are covered.

## Evidence and scope

The 2026-09-30 review compared current Vue call sites with Laravel routes, request validation, resources, and frontend configuration. It was static inspection only. No production or staging browser smoke test was run as part of that review. The separate backend Phase 4 production-readiness plan remains separate because its MySQL, Redis, queue, scheduler, Zoom, and Paddle checks are backend/infrastructure release gates.
