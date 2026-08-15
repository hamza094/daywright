# API Query Production Readiness Plan

Created: 2026-05-19

This plan converts the API query audit into an incremental implementation roadmap.
The goal is to make DayWright's public REST API strict, consistent, and production-ready without forcing a repo-wide rewrite.

Work through the phases in order.
Keep behavior changes small, test each phase in isolation, and update public documentation whenever the request contract changes.

## Purpose

- Turn the audit findings into a phased rollout that can be implemented one PR at a time.
- Standardize filtering, sorting, includes, pagination, and query validation behavior across the public API.
- Keep the existing Laravel architecture: FormRequest validation, API resources, repositories/services, and selective Spatie Query Builder usage.

## Current Assessment

- The API is partially consistent.
- The strongest query surfaces already exist on admin users, admin tasks, admin projects, public projects, notifications, and project activities.
- The biggest gaps are silent query-parameter ignores, mixed pagination behavior, unpaginated collection endpoints, resource and eager-loading drift, documentation drift, and uneven filter/sort semantics.

## Goals

- One strict public query contract for collection endpoints.
- One stable response shape per endpoint.
- One safe relationship loading and serialization policy.
- One clear compatibility policy for legacy aliases and documented exceptions.
- One consistent validation error envelope across all query failures.
- Minimal, safe, production-grade changes instead of broad refactors.

## Non-Goals

- A repo-wide migration to Spatie Query Builder.
- Enabling public `include` support globally.
- Refactoring unrelated write endpoints.
- Introducing breaking route renames without an explicit compatibility plan.

## Target Public Query Contract

### Filtering

- Use `filter[field]=value` for collection endpoints.
- Accept only documented filter keys.
- Reject unknown filter keys with `422 Unprocessable Entity`.
- Reject invalid filter values with `422 Unprocessable Entity`.
- Return `200 OK` with `data: []` when a valid filter matches no records.

### Sorting

- Use `sort=field` for ascending order.
- Use `sort=-field` for descending order.
- Accept only documented sort keys.
- Reject `sort` entirely on endpoints that do not support sorting.
- Define and document a default sort wherever sorting is supported.

### Includes

- Default to no public `include` support.
- Reject `include` unless the endpoint explicitly documents allowed include names.
- If includes are added later, expose public API aliases instead of internal relation names.
- Keep sensitive or heavy relationships server-controlled unless there is a strong public API reason to expose them.

### Pagination

- Use `page` and `per_page` for paginated endpoints.
- Keep `per_page` within a documented safe range, normally `1..100`.
- Do not switch response shape based on one filter.
- Every collection endpoint must be exactly one of these:
  - paginated
  - intentionally fixed-size and documented
  - intentionally unpaginated because the dataset is bounded and product-approved

### Invalid Query Parameters

- Reject unsupported top-level query parameters with `422 Unprocessable Entity`.
- Reject unsupported nested query keys with `422 Unprocessable Entity`.
- Do not silently ignore `include`, `sort`, `filter[...]`, `page`, `per_page`, or arbitrary extras such as `random=value`.

### Error Envelope

- Keep the existing DayWright API error shape:

```json
{
  "message": "Validation failed.",
  "code": "validation_error",
  "errors": {
    "sort": ["The selected sort is invalid."]
  },
  "meta": {}
}
```

## Architecture Decisions

- Keep FormRequest validation as the public contract boundary.
- Keep `ApiQueryRequest` as the shared base for query primitives and strict validation.
- Use Spatie Query Builder selectively for simple DB-backed indexes where it reduces boilerplate.
- Keep DTO + service/repository/model query-builder composition for domain-specific query paths.
- Keep API resources as the only serialization layer for public responses.

## Endpoint Family Matrix

| Endpoint family                          | Current state                         | Target state                                           |
| ---------------------------------------- | ------------------------------------- | ------------------------------------------------------ |
| Public projects index                    | Strict request + paginated            | Keep, tighten semantics and docs                       |
| Admin projects index                     | Strict request + paginated            | Keep, tighten resource safety                          |
| Admin tasks index                        | Strict request + paginated + Spatie   | Keep, tighten docs and tests                           |
| Admin users index                        | Strict request + paginated + Spatie   | Keep, tighten docs and tests                           |
| Notifications index                      | Strict filter + paginated             | Keep, reject all unsupported top-level params          |
| Project activity feed                    | Strict filter + paginated             | Keep, fix eager loading and actor serialization        |
| Project task index                       | Mixed paginated/unpaginated shape     | Normalize to one stable contract                       |
| Meetings index                           | Paginated with legacy top-level alias | Keep or deprecate alias explicitly and document it     |
| Project conversations index              | Unpaginated                           | Paginate                                               |
| Public users index                       | Unpaginated, no query request         | Decide whether to paginate or narrow exposure          |
| Dashboard projects                       | Fixed-size list of 3                  | Keep fixed-size, document clearly                      |
| Dashboard tasks                          | Intentional unpaginated filtered list | Keep as bounded exception, enforce strict param policy |
| Dashboard activities                     | Top-level date-range exception        | Keep documented exception, enforce strict param policy |
| Invitation search and task member search | Dedicated top-level search endpoints  | Keep search pattern, reject unrelated query params     |
| User invitations and scheduled messages  | Unpaginated collection reads          | Classify as bounded or paginate                        |

## Audit Issue to Phase Map

| Audit issue | Summary                                                                    | Target phase        |
| ----------- | -------------------------------------------------------------------------- | ------------------- |
| 1           | Unsupported top-level query params are silently ignored                    | Phase 1             |
| 2           | Task index changes response shape and keeps a legacy alias                 | Phase 2 and Phase 5 |
| 3           | Some public list endpoints are unpaginated and unbounded                   | Phase 2             |
| 4           | Project activity feed has a query/resource mismatch                        | Phase 3             |
| 5           | Some resources serialize loaded relations directly                         | Phase 3             |
| 6           | Public naming and docs drift across endpoints                              | Phase 3 and Phase 6 |
| 7           | Scramble overview is too vague and meetings are excluded from docs         | Phase 6             |
| 8           | Sort behavior is allowlisted but some likely hot columns are not optimized | Phase 4             |
| 9           | Filter semantics differ across endpoints                                   | Phase 4             |

## Phase 0 - Optional Compatibility Discovery

Status:

- Optional but recommended if external clients may rely on previously ignored query parameters.

Goal:

- Reduce the risk of breaking existing API consumers when moving from silent ignore to strict rejection.

Tasks:

- Review API consumer expectations before hardening request validation.
- Optionally add temporary instrumentation for unknown query keys on public read endpoints.
- Decide which legacy aliases are temporary compatibility behavior versus long-term exceptions.
- Freeze the target query contract before making behavior changes.

Review first:

- `app/Http/Requests/Api/V1/ApiQueryRequest.php`
- `docs/API_QUERY_STANDARDIZATION_PLAN.md`
- `config/scramble_overview.php`

Exit criteria:

- Unknown-parameter and compatibility decisions are explicit.
- The team agrees on which query behaviors are temporary compatibility paths.

## Phase 1 - Shared Strict Query Validation

Issues addressed:

- Audit issue 1

Goal:

- Every query-bearing endpoint explicitly defines which top-level parameters it supports.
- Unsupported query parameters return validation errors instead of being silently ignored.

Tasks:

- Extend `ApiQueryRequest` with a shared allowlist mechanism for supported top-level query parameters.
- Keep `include`, `fields`, and `append` rejection in the shared base.
- Add explicit rejection for unsupported `sort`, `page`, `per_page`, and arbitrary keys on endpoints that do not support them.
- Move dedicated query request classes that do not use the shared base onto the same strict top-level-parameter policy.
- Standardize validation messages for unsupported query parameters.

Likely files:

- `app/Http/Requests/Api/V1/ApiQueryRequest.php`
- `app/Http/Requests/Api/V1/Notifications/NotificationIndexRequest.php`
- `app/Http/Requests/Api/V1/Zoom/MeetingIndexRequest.php`
- `app/Http/Requests/Api/V1/Task/TaskIndexRequest.php`
- `app/Http/Requests/Api/V1/Project/ProjectInvitationIndexRequest.php`
- `app/Http/Requests/Api/V1/User/UserTasksRequest.php`
- `app/Http/Requests/Api/V1/User/InvitationUserSearchRequest.php`
- `app/Http/Requests/Api/V1/Task/TaskMemberSearchRequest.php`
- `app/Http/Requests/Api/V1/User/UserActivitiesRequest.php`
- `app/Http/Requests/Api/V1/Dashboard/DashboardChartDataRequest.php`

Recommended tests:

- Reject `random=value` on every reviewed query endpoint.
- Reject `sort=password` on endpoints that do not support sorting.
- Reject `include=passwords` on search and exception endpoints that currently ignore it.
- Keep the current `message`, `code`, `errors`, `meta` validation envelope.

Recommended test files:

- `tests/Feature/Api/V1/Notifications/UserNotificationInboxTest.php`
- `tests/Feature/Api/V1/Tasks/TaskTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingReadTest.php`
- `tests/Feature/Api/V1/InvitationTest.php`
- `tests/Feature/Api/V1/SearchableTest.php`
- `tests/Feature/Api/V1/Tasks/TaskMemberManagementTest.php`
- `tests/Feature/Api/V1/Dashboard/UserActivitiesTest.php`
- `tests/Feature/Api/V1/Dashboard/DashboardChartDataTest.php`

Exit criteria:

- No reviewed query endpoint silently ignores unsupported top-level query parameters.
- Search, analytics, and exception endpoints follow the same strict validation policy.
- Validation failures continue to use the shared API error envelope.

## Phase 2 - Pagination Contract Stabilization

Issues addressed:

- Audit issues 2 and 3

Goal:

- Every collection endpoint has one stable response shape and a clearly documented pagination policy.

Tasks:

- Normalize `GET /projects/{project}/tasks` to one stable list contract.
- Recommended decision: keep the task index on one route but always return a paginated payload, including archived tasks.
- Keep `request=archived` only as a temporary compatibility alias if needed.
- Add a dedicated request and pagination policy to the public users index if that endpoint remains public.
- Paginate the project conversation index.
- Review unpaginated public collections and classify them as paginated, fixed-size, or intentional bounded exceptions.
- Standardize `per_page` defaults and maximums by endpoint family.
- Ensure empty paginated responses keep `data`, `meta`, and `links`.

Likely files:

- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Services/Task/TaskService.php`
- `app/Http/Requests/Api/V1/Task/TaskIndexRequest.php`
- `app/Http/Controllers/Api/V1/User/UserController.php`
- `app/Services/User/UserService.php`
- `app/Http/Controllers/Api/V1/Project/ConversationController.php`
- `app/Repository/Api/V1/ConversationRepository.php`
- `app/Http/Controllers/Api/V1/User/UserInvitationsController.php`
- `app/Http/Controllers/Api/V1/Project/ScheduledProjectMessagesController.php`
- `app/Services/Dashboard/UserProjectListingService.php`

Decisions required:

- Whether the public users index should stay public and paginated or be narrowed in scope.
- Whether user invitations and scheduled messages are truly bounded enough to stay unpaginated.

Recommended tests:

- Task index keeps one stable response shape for active and archived results.
- Public users index is paginated if it remains public.
- Conversation index returns `data`, `meta`, and `links`.
- Empty paginated lists still return `meta` and `links`.

Recommended test files:

- `tests/Feature/Api/V1/Tasks/TaskTest.php`
- `tests/Feature/Api/V1/Projects/ProjectIndexTest.php`
- Add or update a conversations index test file
- Add or update a public users index test file

Exit criteria:

- Every public collection endpoint is classified and implemented as paginated, fixed-size, or intentional bounded exception.
- The task index no longer changes its transport shape based on one filter.
- Large public collection endpoints no longer return unbounded results by default.

## Phase 3 - Resource Safety and Eager-Loading Hygiene

Issues addressed:

- Audit issues 4, 5, and part of 6

Goal:

- Public responses are serialized intentionally through resources and loaded intentionally through query code.

Tasks:

- Fix the project activity feed query/resource mismatch by eagerly loading the actor relation.
- Replace raw relation serialization with explicit API resources where relations are currently returned directly.
- Audit `whenLoaded()` usage against actual load profiles for optional relationships.
- Freeze public names for relationship-like fields such as `owner`, `user`, `members`, and `assignee`.
- Keep public includes disabled by default while aligning field names for future alias-based include support.

Likely files:

- `app/Services/Project/ProjectActivityListingService.php`
- `app/Http/Resources/Api/V1/ActivityResource.php`
- `app/Http/Resources/Api/V1/Admin/ProjectResource.php`
- `app/Http/Resources/Api/V1/Project/ProjectResource.php`
- `app/Http/Resources/Api/V1/Zoom/MeetingResource.php`
- `app/Http/Resources/Api/V1/Project/ProjectInvitationResource.php`
- `app/Http/Resources/Api/V1/User/UserTasksResource.php`

Recommended tests:

- Activity feed returns actor data through an explicit resource contract.
- No raw relation serialization leaks unintended fields.
- Optional relationships are present only when intentionally loaded.

Recommended test files:

- `tests/Feature/Api/V1/ActivityTest.php`
- `tests/Feature/Api/V1/Admin/ProjectsTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingReadTest.php`
- `tests/Feature/Api/V1/InvitationTest.php`

Exit criteria:

- The activity feed no longer depends on lazy loading for actor serialization.
- Resources no longer serialize loaded Eloquent relations directly where a public resource contract should exist.
- Public field names are explicitly chosen and consistent with the resource surface.

## Phase 4 - Filter and Sort Semantics

Issues addressed:

- Audit issues 8 and 9, plus remaining sort strictness gaps

Goal:

- Filtering and sorting behave predictably across endpoints and avoid obvious scale risks.

Tasks:

- Standardize wildcard escaping and boolean normalization across search filters.
- Keep canonical `sort=field` and `sort=-field` syntax only.
- Reject `sort` on endpoints that do not support sorting.
- Ensure every sortable endpoint has an explicit default sort.
- Review searchable and sortable columns with `EXPLAIN` before adding new indexes.
- Focus on likely hot columns such as:
  - `projects.created_at`
  - `projects.name`
  - `tasks.created_at`
  - `tasks.title`
  - `users.created_at`
  - `users.name`
- Keep Spatie usage selective and expand only where it clearly simplifies a DB-backed list endpoint.

Likely files:

- `app/QueryBuilder/ProjectQueryBuilder.php`
- `app/QueryBuilder/TaskQueryBuilder.php`
- `app/QueryBuilder/Filters/AdminUserSearchFilter.php`
- `app/QueryBuilder/Filters/AdminTaskSearchFilter.php`
- `app/Http/Requests/Api/V1/Admin/UserFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/TaskFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/ProjectFilterRequest.php`
- `app/Http/Requests/Api/V1/Project/DashboardProjectRequest.php`

Recommended tests:

- Wildcard characters do not alter search behavior differently across endpoint families.
- Sort allowlists reject unsupported columns.
- Default sorts remain stable when params are omitted.

Recommended test files:

- `tests/Feature/Api/V1/Admin/UsersTest.php`
- `tests/Feature/Api/V1/Admin/TasksTest.php`
- `tests/Feature/Api/V1/Admin/ProjectsTest.php`
- `tests/Feature/Api/V1/Projects/ProjectIndexTest.php`

Exit criteria:

- Search semantics are consistent across public and admin list endpoints.
- Sort behavior is explicit, documented, and validated.
- Any index additions are backed by query review instead of guesswork.

## Phase 5 - Compatibility Aliases and Exception Policy

Issues addressed:

- Audit issue 2 and the compatibility portion of audit issue 6

Goal:

- Keep only intentional, documented compatibility behavior.

Tasks:

- Catalog all legacy query aliases and domain-specific exceptions.
- Recommended compatibility list to review:
  - `request=archived` on the task index
  - `request=previous` on the meetings index
  - top-level `start_date` and `end_date` on dashboard activities
  - top-level `year` and `month` on dashboard chart data
- Decide which compatibility paths are temporary deprecations and which are long-term public exceptions.
- Remove or correct stale docs that advertise unsupported shorthand parameters.
- Add targeted tests for any alias kept during the deprecation window.

Likely files:

- `app/Http/Requests/Api/V1/Task/TaskIndexRequest.php`
- `app/Http/Requests/Api/V1/Zoom/MeetingIndexRequest.php`
- `app/Http/Requests/Api/V1/User/UserActivitiesRequest.php`
- `app/Http/Requests/Api/V1/Dashboard/DashboardChartDataRequest.php`
- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectInvitationController.php`

Recommended tests:

- Legacy aliases behave exactly as documented.
- Removed aliases fail validation once deprecation ends.

Recommended test files:

- `tests/Feature/Api/V1/Tasks/TaskTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingReadTest.php`
- `tests/Feature/Api/V1/Dashboard/UserActivitiesTest.php`
- `tests/Feature/Api/V1/Dashboard/DashboardChartDataTest.php`

Exit criteria:

- Every remaining compatibility alias is intentional, documented, and tested.
- No stale shorthand documentation remains in code annotations or docs.

## Phase 6 - Scramble and Public Documentation Alignment

Issues addressed:

- Audit issue 7 and the documentation portion of audit issue 6

Goal:

- The global API docs describe the real shared query contract and all intentional exceptions.

Tasks:

- Update `config/scramble_overview.php` with the global query contract.
- Document filtering, sorting, includes, pagination, invalid query-parameter behavior, and the actual validation envelope.
- Fix stale `QueryParameter` annotations that contradict runtime validation.
- Decide whether meetings should remain excluded from Scramble.
- If meetings remain excluded, document them elsewhere as a public API exception.
- Ensure the overview distinguishes between paginated, fixed-size, and intentional exception endpoints.

Likely files:

- `config/scramble_overview.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Api/V1/Task/TaskController.php`
- `app/Http/Controllers/Api/V1/Project/ProjectInvitationController.php`

Recommended tests:

- Scramble overview text matches actual validation behavior.
- Endpoint-level annotations do not advertise unsupported shorthand params.

Exit criteria:

- The global docs clearly explain the shared query contract.
- Endpoint docs no longer contradict request validation rules.
- Public query exceptions are visible and intentional.

## Phase 7 - Contract Tests and Release Gates

Issues addressed:

- All prior phases

Goal:

- Lock in the production-ready contract with focused tests and rollout gates.

Tasks:

- Add focused contract tests for:
  - unsupported top-level params
  - unsupported `sort` and `include`
  - unsupported `filter[...]` keys
  - valid empty results
  - paginated response shape consistency
  - compatibility alias behavior
  - resource safety on optionally loaded relations
- Keep each phase scoped to the smallest relevant test set.
- After each phase passes, decide whether to run the full test suite before the next phase.

Recommended release checklist:

- Focused PHPUnit files pass for the touched slice.
- Public docs are updated for any changed request contract.
- No endpoint silently ignores unsupported query parameters unless explicitly approved as an exception.
- No endpoint changes its transport shape unexpectedly.

Exit criteria:

- The public query contract is covered by stable feature tests.
- Regression risk is reduced to narrow, understandable slices.

## Recommended Order

1. Phase 0 if compatibility discovery is needed.
2. Phase 1 shared strict query validation.
3. Phase 2 pagination contract stabilization.
4. Phase 3 resource safety and eager-loading hygiene.
5. Phase 4 filter and sort semantics.
6. Phase 5 compatibility aliases and exception policy.
7. Phase 6 Scramble and public documentation alignment.
8. Phase 7 contract tests and release gates.

## Recommended First Implementation Slice

If you want the safest first code phase, start with Phase 1.

Why:

- It delivers immediate public API consistency.
- It does not require route redesign.
- It reduces silent contract drift before larger pagination or serialization changes.
- It gives the rest of the phases a stricter foundation.

Start Phase 1 with these requests first:

- `NotificationIndexRequest`
- `MeetingIndexRequest`
- `TaskIndexRequest`
- `ProjectInvitationIndexRequest`
- `InvitationUserSearchRequest`
- `TaskMemberSearchRequest`

That slice will tighten the most visible public inconsistencies with minimal response-payload churn.
