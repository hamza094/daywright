# API Query Standardization Plan

Created: 2026-05-16

This plan standardizes filtering, sorting, includes, and pagination across DayWright's API.
`spatie/laravel-query-builder` is now installed and available in the application, but package availability does not change the default design rule for this plan: simplicity comes first.

Work through the phases in order.
Do not treat package installation as an automatic repo-wide migration.
Stabilize the query contract, pagination policy, and endpoint classes first, then apply Spatie only where it is actually needed and makes the endpoint simpler.

## Implementation Status

- Phase 1 is implemented for the Spatie-installed baseline:
  - package availability and default configuration are recorded below
  - the public query contract is frozen for collection and collection-like read endpoints
  - current collection endpoints are classified into Class A, B, or C
  - target pagination policy is recorded per endpoint family
  - date-range, sorting, include, fields, append, and legacy alias decisions are recorded below
- Phase 2 is not implemented for the Spatie path:
  - current request-side query primitives still live in `ApiQueryRequest`
  - most production endpoints do not use `Spatie\QueryBuilder\QueryBuilder` yet
  - `GET /api/v1/notifications` was reviewed and intentionally kept on the simpler house-style query path because Spatie did not improve clarity there
- Phase 2 is implemented as shared groundwork:
  - `ApiQueryRequest` now exposes shared `allowedFilters()`, `allowedSorts()`, `defaultSorts()`, and `allowedIncludes()` hooks for future Spatie-backed requests
  - `GET /api/v1/admin/users` is the first Class A reference candidate for direct request-to-builder mapping
  - admin project `applied_filters` label generation is separated from repository query composition
  - DTOs remain the preferred path for admin project filters where normalization and UI label metadata still add value
- Phase 3 is partially implemented:
  - DB-backed pagination has been moved out of in-memory slices for user projects and project activities
  - the meeting index now uses a dedicated query request for pagination and legacy state normalization
  - Laravel's native paginated resource collections replaced the custom `ApiPaginator`
- Phase 4 is implemented for the controller extraction slice:
  - admin user index query logic now lives in `App\Repository\Admin\UserRepository`
  - admin dashboard recent activities now load through `DashboardService` and `DashboardRepository`
  - project activity listing now paginates through `ProjectActivityListingService`
  - the reviewed list endpoints in this phase no longer use raw `Request` objects for query input
- Phase 5 is implemented for the current filter and sort standardization slice:
  - admin project, admin task, and admin user indexes now expose explicit request-side filter/sort allowlists
  - admin project sorting uses canonical sort values; request layer no longer maps `asc`/`desc` aliases — clients must use `field` or `-field` (for example `created_at` or `-created_at`)
  - user project sorting uses canonical sort values; request layer no longer maps `latest`/`oldest` aliases — clients must use `field` or `-field` (for example `created_at` or `-created_at`)
  - reusable sort behavior now lives in project and task query builders or dedicated repository helpers
  - admin project applied-filter sort labels are now human-readable and remain separate from query composition
- Phase 6 is implemented for the reviewed eager-loading policy slice:
  - user project list queries now use an explicit summary load profile and no longer eager load owner data that the list resource does not serialize
  - project detail responses now use the same explicit resource load profile as create and update responses
  - meeting responses now use a named response load profile so list and single-meeting reads stay aligned
  - conversation responses now use an explicit server-controlled response load profile for `user` and `project:id,slug`
  - public `include` support is still not introduced on the reviewed non-admin collection endpoints; unsupported `include` params are rejected explicitly instead of being ignored
- Phase 7 is implemented for the reviewed domain-specific exception slice:
  - dashboard tasks remain intentionally unpaginated, but now require canonical nested `filter[...]` boolean inputs and reject the legacy top-level boolean aliases
  - project activities keep the canonical `filter[type]` contract and reject the legacy top-level type aliases
  - dashboard chart data remains an analytics-style exception that uses top-level `year` and `month`, and now validates that `month` is only supplied with `year`
  - dashboard activities remain an intentional date-window exception using top-level `start_date` and `end_date`
  - project insights remain an intentional domain-specific exception using `sections[]` instead of the generic filter grammar
- Phase 8 is implemented as a limited Spatie pilot:
  - `GET /api/v1/admin/users` and `GET /api/v1/admin/tasks` are the current selective pilot endpoints wired through `Spatie\QueryBuilder\QueryBuilder`
  - the pilot remains request-validated first: filters and sorts are still explicitly validated in the dedicated request classes
  - package-only params that are still out of scope for this plan (`include`, `fields`, and `append`) are rejected explicitly on the pilot endpoint
  - the pilot decision is yes for selective usage only; meeting index and notification index remain on the house style for now
- Phase 9 is implemented:
  - the remaining reviewed query requests now reject unsupported `include`, `fields`, and `append` parameters explicitly instead of silently ignoring them
  - canonical nested `filter[...]` keys are now enforced across the reviewed endpoints; legacy top-level filter aliases and legacy scalar `filter=value` shorthands are rejected explicitly
  - focused contract tests cover the tightened filter and sort behavior for the reviewed endpoint families
  - the remaining intentional compatibility exceptions are documented rather than left as accidental drift: `request=archived` on the task index and `request=previous` on the meeting index
- Current package decision: `spatie/laravel-query-builder` is installed and configured with default parameter names.
- Adoption decision: apply Spatie only where it is needed and it measurably simplifies the endpoint; otherwise keep the existing house-style query path.

## Goal

- One predictable query contract for list endpoints.
- One consistent pagination contract.
- One explicit relationship loading policy.
- One package-aligned query grammar for endpoints that are good Spatie candidates.
- Query concerns live in requests, query builders, repositories, and read services instead of controllers.
- Keep request validation and authorization explicit even when query composition moves to Spatie.

## Scope

In scope:

- `app/Http/Requests/Api/V1/**`
- `app/Http/Controllers/Api/V1/**`
- `app/Repository/**`
- `app/Services/**` for list, search, filter, sort, and pagination paths
- `app/QueryBuilder/**`
- `app/Http/Resources/Api/V1/**`
- API query documentation and conventions

Out of scope:

- Create and update request validation unrelated to read endpoints
- Webhook payload parsing
- Broad frontend state refactors
- Repo-wide package adoption during the early phases of this plan

## Final Decision For This Plan

- Primary architecture: `FormRequest` + thin `ApiQueryRequest` for validation, pagination, and minor normalization + the simplest readable query composition for the endpoint.
- Spatie Query Builder is an optional tool for eligible DB-backed list endpoints only when it reduces boilerplate and improves clarity over the existing house style.
- DTO + repository/service/model query builder remains the preferred approach for domain-specific, collection-backed, or already-clear query paths.
- Not recommended: a repo-wide Spatie migration or forcing Spatie onto endpoints that are already simple and readable without it.

## Why This Direction

The codebase already has a real query architecture in many places.
The main problem is not unsafe dynamic SQL or lack of structure.
The main problem is drift in public query conventions, repeated normalization code, mixed pagination policies, and inconsistent eager loading.

Installing Spatie helps with allowlisted filters, sorts, and includes on DB-backed index endpoints, and the default parameter names already match the direction of this plan.
It still does not replace request authorization, request validation, pagination policy, eager-loading policy, or domain-specific collection transforms.

That means the package should be used as an implementation tool only when it makes a query surface simpler to read and maintain, not as a reason to rewrite every endpoint that could technically use it.

Simplicity is the first priority in package adoption decisions. If the existing request + service/repository query path is already clear, explicit, and easy to maintain, that path should stay in place.

## Target Query Contract

### Installed Package Baseline

- `spatie/laravel-query-builder` v7 is installed.
- `config/query-builder.php` keeps the default parameter names: `filter`, `sort`, `include`, `fields`, and `append`.
- Invalid filter, sort, and include exceptions remain enabled.
- Multi-value filter splitting stays enabled with the comma delimiter.
- Package installation does not automatically make `include`, `fields`, or `append` public on any endpoint; each endpoint must opt in through an explicit allowlist.

### Filtering

- Use `filter[...]` for general list endpoints.
- Canonical examples:
  - `filter[search]`
  - `filter[state]`
  - `filter[status]`
  - `filter[from]`
  - `filter[to]`
  - `filter[type]`
  - `filter[member]`
  - `filter[abandoned]`
- Keep standalone `search` only for dedicated autocomplete and lookup endpoints.
- New Spatie-backed endpoints must accept canonical nested filter keys only.
- If any legacy alias remains on a non-Spatie endpoint, normalize it only inside the request layer until it is removed.
- Filterable endpoints must validate and whitelist all supported filters explicitly.
- Query code must never read directly from global request state.
- Spatie should not be introduced for a filter surface that is already smaller and clearer with ordinary Eloquent or repository code.

### Sorting

- Use a single `sort` parameter for sortable endpoints.
- Prefer field and alias syntax instead of direction-only syntax.
- Target examples:
  - `sort=-created_at`
  - `sort=name`
  - `sort=health_score`
- Every sortable endpoint must define:
  - allowed sorts
  - a default sort
  - any legacy aliases kept temporarily for compatibility
- Repositories and services must not accept arbitrary request-driven column names.
- Spatie-backed endpoints should express sort allowlists with `allowedSorts()` and `defaultSort()`.

### Includes

- Default stance: server-controlled eager loading unless an endpoint explicitly supports `include`.
- If `include` is exposed, it must be whitelisted per endpoint.
- Use this decision rule when reviewing a relationship:
  - always needed in the endpoint response: eager load it in the query layer
  - optional for the API consumer: consider `?include=...`
  - sensitive or heavy: only expose it behind an explicit `?include=...`
  - never public: reject `include` in request validation
- Admin-related routes do not expose public `include` support in this plan.
- Resources should use `whenLoaded` for optional relationships.
- Services and repositories should load only the relations needed by the response resource or documented load profile.
- Even though the package supports `include`, `fields`, and `append`, those remain opt-in per endpoint and are not automatically public in Phase 1.
- Prefer dedicated request validation over Spatie include wiring when an endpoint only needs to reject unsupported `include` parameters.

### Pagination

- Use `page` and `per_page` for paginated endpoints.
- Validate `per_page` everywhere with the same min and max rules unless an endpoint has a documented exception.
- Every list endpoint must be one of these three categories:
  - paginated
  - intentionally fixed-size and documented
  - intentionally unpaginated because the dataset is bounded and product-approved
- Keep one paginated response shape with `data`, `meta`, and `links`.
- Do not switch a single endpoint between paginated and unpaginated response shapes based on one filter unless that behavior is explicitly retained as a long-term contract.

## Endpoint Classes

### Class A - Pure DB-backed index endpoints

These are the best candidates for strict query standardization and a future Spatie pilot if one is still needed.

Examples:

- user projects
- admin projects
- admin tasks
- admin users
- notifications
- meetings
- conversations

### Class B - Domain-specific query endpoints

These should keep custom query logic but adopt the same public contract where practical.

Examples:

- dashboard tasks
- project activities
- project insights
- dashboard chart data
- dashboard activities

### Class C - Bounded lookup and support list endpoints

These may stay fixed-size or unpaginated if the result set is intentionally capped, product-bounded, or reference-only.

Examples:

- task member search
- invitation user search
- stage lists
- status lists
- token lists
- pending invitation lists

## Phase 1 - Freeze The Query Contract

Status:

- Implemented

Goal:

- Establish one API query language before changing implementation details.

Tasks:

- [x] Define this file as the temporary source of truth for query conventions.
- [x] Classify current collection and collection-like read endpoints into Class A, B, or C.
- [x] Choose the canonical date-range contract for general list filtering.
- [x] Choose the canonical sorting contract for sortable endpoints.
- [x] Decide which legacy aliases remain during migration and which are removed immediately.
- [x] Decide whether `include` remains entirely internal for now or becomes a public query parameter on selected endpoints.
- [x] Decide which endpoints are intentionally fixed-size or intentionally unpaginated.

Phase 1 scope clarification:

- The pagination taxonomy in this phase applies to collection and collection-like read endpoints.
- Single-resource reads and aggregate reads are intentionally outside the `paginated` vs `fixed-size` vs `intentionally unpaginated` classification.
- Examples outside this taxonomy include current-user payloads, subscription snapshots, project detail payloads, project limits, dashboard KPI aggregates, dashboard chart aggregates, and export/download endpoints.

### Phase 1 Decisions

#### General contract decisions

- New and refactored general list endpoints must use nested `filter[...]` inputs for filtering. This matches the installed Spatie parameter configuration.
- Standalone `search` remains allowed only for bounded lookup endpoints.
- New and refactored sortable endpoints must expose `sort=<alias>` and `sort=-<alias>`. Sort tokens are case-sensitive and must use lowercase snake_case (for example `created_at` or `-created_at`).
- Direction-only public sort values such as `sort=asc` and `sort=desc` are deprecated. The canonical API sort form is `sort=field` (ascending) or `sort=-field` (descending), e.g. `sort=created_at` or `sort=-created_at`. Sort tokens are case-sensitive and must be lowercase snake_case.
- Public `include` support is not introduced in Phase 1 even though the package can support it.
- Public `fields` and `append` support are also not introduced in Phase 1.
- Relationship loading remains server-controlled until Phase 6 defines any endpoint-level include whitelist.
- New collection endpoints default to paginated unless they are explicitly approved as fixed-size or intentionally unpaginated.

#### Canonical date-range contract

- General list endpoints standardize on `filter[from]` and `filter[to]`.
- The date range is inclusive.
- Both keys must be supplied together.
- Top-level `start_date` and `end_date` remain allowed only for analytics-style date-window endpoints.
- Top-level `year` and `month` remain allowed only for aggregate analytical endpoints.

#### Canonical sorting contract

- General list endpoints standardize on explicit field or alias sorts.
- Preferred examples:
  - `sort=-created_at`
  - `sort=name`
  - `sort=health_score`
- Default sort policy by endpoint family:
  - general DB-backed indexes default to newest-first unless a clearer domain alias is defined
  - bounded reference lists default to stable name or id ordering and usually do not expose public sorting
  - dashboard summary endpoints may keep fixed internal ordering when they are not general indexes

#### Include policy

- No public `include` query parameter is introduced in Phase 1.
- Controllers, services, and repositories keep relationship loading internal and explicit.
- Resources should continue to use `whenLoaded` for optional relations where practical.
- Phase 6 will decide whether any endpoints should expose whitelisted `include` support.
- `fields` and `append` remain inactive until a later phase explicitly introduces and documents them.

#### Legacy alias policy

- New Spatie-backed endpoints must use canonical keys only.
- Any remaining legacy aliases are allowed only in request normalization layers on non-Spatie endpoints until those slices are refactored.
- No controller, repository, or service may read new alias keys directly.
- No new aliases should be introduced after Phase 1.
- Existing aliases are temporary compatibility behavior and should be removed endpoint-by-endpoint as those slices are refactored.

| Endpoint family                              | Canonical input                                                                                              | Temporary legacy alias policy                                                                                                  |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------ |
| Dashboard and admin project-like indexes     | `filter[search]` and other nested `filter[...]` keys                                                         | Top-level aliases have been removed from the refactored requests; future Spatie-backed endpoints must keep canonical keys only |
| Notifications and project invitation indexes | `filter[status]`                                                                                             | Top-level `status` has been removed from the refactored requests                                                               |
| Dashboard tasks                              | `filter[user_created]`, `filter[task_assigned]`, `filter[completed]`, `filter[overdue]`, `filter[remaining]` | Top-level boolean aliases have been removed and are rejected in request validation                                             |
| Task index                                   | `filter[state]=archived`                                                                                     | Keep `request=archived` only in request normalization                                                                          |
| Meeting index                                | target `filter[state]=previous` or `filter[state]=scheduled`                                                 | Keep `request=previous` as an intentional compatibility exception for now                                                      |
| Invitation user search                       | `search`                                                                                                     | No legacy alias remains; reject non-canonical keys                                                                             |

### Phase 1 Endpoint Inventory

#### Paginated endpoints

| Endpoint                                            | Class | Phase 1 policy | Current implementation note                                                     |
| --------------------------------------------------- | ----- | -------------- | ------------------------------------------------------------------------------- |
| `GET /api/v1/projects`                              | A     | paginated      | Already paginated and now paginates at the database layer                       |
| `GET /api/v1/admin/projects`                        | A     | paginated      | Already paginated                                                               |
| `GET /api/v1/admin/tasks`                           | A     | paginated      | Already paginated                                                               |
| `GET /api/v1/admin/users`                           | A     | paginated      | Already paginated and now queries through `App\Repository\Admin\UserRepository` |
| `GET /api/v1/notifications`                         | A     | paginated      | Already paginated                                                               |
| `GET /api/v1/users`                                 | A     | paginated      | Currently unpaginated and should be brought under the standard policy           |
| `GET /api/v1/projects/{project}/tasks`              | A     | paginated      | Currently mixed because archived task requests bypass pagination                |
| `GET /api/v1/projects/{project}/meetings`           | A     | paginated      | Already paginated and now validated by `MeetingIndexRequest`                    |
| `GET /api/v1/projects/{project}/conversations`      | A     | paginated      | Currently unpaginated                                                           |
| `GET /api/v1/projects/{project}/messages/scheduled` | A     | paginated      | Currently unpaginated                                                           |
| `GET /api/v1/projects/{project}/activities`         | B     | paginated      | Already paginated and now paginates at the database layer                       |

#### Fixed-size endpoints

| Endpoint                                                     | Class | Phase 1 policy | Current implementation note                  |
| ------------------------------------------------------------ | ----- | -------------- | -------------------------------------------- |
| `GET /api/v1/dashboard/projects`                             | B     | fixed-size     | Intentionally capped at 3 recent projects    |
| `GET /api/v1/users/search`                                   | C     | fixed-size     | Intentionally capped at 5 lookup results     |
| `GET /api/v1/projects/{project}/tasks/{task}/members/search` | C     | fixed-size     | Intentionally capped at 5 lookup results     |
| `GET /api/v1/admin/dashboard/activities`                     | B     | fixed-size     | Intentionally capped at 15 recent activities |

#### Intentionally unpaginated endpoints

| Endpoint                                     | Class | Phase 1 policy            | Current implementation note                                           |
| -------------------------------------------- | ----- | ------------------------- | --------------------------------------------------------------------- |
| `GET /api/v1/dashboard/tasks`                | B     | intentionally unpaginated | Treated as a dashboard workload summary rather than a scrolling index |
| `GET /api/v1/dashboard/activities`           | B     | intentionally unpaginated | Date-window analytics feed; bounded by the requested activity range   |
| `GET /api/v1/users/me/invitations`           | C     | intentionally unpaginated | Pending invitation set is treated as a bounded account list           |
| `GET /api/v1/projects/{project}/invitations` | C     | intentionally unpaginated | Pending invitation set is treated as a bounded support list           |
| `GET /api/v1/api-tokens`                     | C     | intentionally unpaginated | User-owned token list is treated as a bounded account list            |
| `GET /api/v1/stages`                         | C     | intentionally unpaginated | Reference data list                                                   |
| `GET /api/v1/admin/stages`                   | C     | intentionally unpaginated | Reference data list                                                   |
| `GET /api/v1/admin/statuses`                 | C     | intentionally unpaginated | Reference data list                                                   |
| `GET /api/v1/task-statuses`                  | C     | intentionally unpaginated | Reference aggregate of task statuses and due-notification options     |

#### Aggregate and single-resource reads outside the pagination taxonomy

These endpoints are frozen outside the collection pagination policy because they return a single resource, a composite dashboard payload, or another non-index response shape.

- `GET /api/v1/users/me`
- `GET /api/v1/users/me/subscription`
- `GET /api/v1/dashboard/chart-data`
- `GET /api/v1/dashboard/insights`
- `GET /api/v1/projects/{project}`
- `GET /api/v1/projects/{project}/limits`
- `GET /api/v1/projects/{project}/insights`
- `GET /api/v1/admin/data`

Review first:

- `app/Http/Requests/Api/V1/DashboardProjectRequest.php`
- `app/Http/Requests/Api/V1/Admin/ProjectFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/TaskFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/UserFilterRequest.php`
- `app/Http/Requests/Api/V1/NotificationIndexRequest.php`
- `app/Http/Requests/Api/V1/TaskIndexRequest.php`
- `app/Http/Requests/Api/V1/ProjectActivityIndexRequest.php`

Exit criteria:

- [x] The team can describe the canonical query contract in one page.
- [x] Every current collection endpoint is classified as paginated, fixed-size, or intentionally unpaginated.
- [x] The legacy query keys still allowed during migration are explicit.

## Phase 2 - Extract Shared Query Primitives

Status:

- Implemented as shared groundwork

Goal:

- Establish the shared request and allowlist primitives that make eligible Class A endpoints cheap to convert to Spatie without removing FormRequest validation.

Tasks:

- [x] Keep a thin shared request base such as `ApiQueryRequest` for `page`, `per_page`, defaults, and any normalization that Spatie does not handle.
- [x] Define shared conventions for Spatie `allowedFilters()`, `allowedSorts()`, and `allowedIncludes()` declarations.
- [x] Decide where DTOs still add value versus direct request-to-builder mapping.
- [x] Keep UI-facing `applied_filters` label generation separate from query composition.
- [x] Choose the first Class A endpoint that will become the Spatie reference implementation.

### Phase 2 Decisions

- `ApiQueryRequest` remains the shared request primitive for pagination defaults and non-Spatie normalization.
- Requests can now declare future Spatie allowlists through `allowedFilters()`, `allowedSorts()`, `defaultSorts()`, and `allowedIncludes()` without changing current endpoint behavior.
- `GET /api/v1/admin/users` is the first reference candidate because its query surface is small, DB-backed, and does not emit UI-facing `applied_filters` labels.
- DTOs still add value for admin projects because that slice still normalizes multiple filter shapes and powers separate UI label metadata.
- UI-facing `applied_filters` labels should be built outside repositories so query composition stays persistence-focused.

Review first:

- `config/query-builder.php`
- `app/Http/Requests/Api/V1/ApiQueryRequest.php`
- `app/Http/Requests/Api/V1/DashboardProjectRequest.php`
- `app/Http/Requests/Api/V1/Admin/ProjectFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/TaskFilterRequest.php`
- `app/Http/Requests/Api/V1/Admin/UserFilterRequest.php`
- `app/Http/Requests/Api/V1/NotificationIndexRequest.php`
- `app/Http/Requests/Api/V1/UserTasksRequest.php`

Exit criteria:

- [x] Shared request helpers are slimmed down to the responsibilities Spatie does not cover.
- [x] One common pattern exists for declaring allowlisted filters, sorts, and includes.
- [x] The first Spatie candidate endpoint can be implemented without inventing new conventions.

## Phase 3 - Standardize Pagination Policy First

Goal:

- Fix the response and scaling inconsistencies before rewriting more filters and sorts.

Tasks:

- Move DB-backed list endpoints away from in-memory pagination when the underlying query can paginate directly.
- Standardize `per_page` defaults by endpoint family instead of ad hoc values.
- Decide whether archived tasks stay inside the main task index or move to a separate explicit endpoint.
- Add a dedicated index request for meetings so pagination and filtering are validated instead of read from a raw request.
- Review unpaginated collection endpoints and either paginate them or explicitly mark them as bounded.

Review first:

- `app/Services/Dashboard/UserProjectListingService.php`
- `app/Http/Controllers/Api/V1/Project/ProjectController.php`
- `app/Services/Project/MeetingService.php`
- `app/Http/Controllers/Api/V1/Project/ZoomMeetingController.php`
- `app/Services/Task/TaskService.php`
- `app/Http/Controllers/Api/V1/User/UserController.php`
- `app/Http/Controllers/Api/V1/User/UserInvitationsController.php`
- `app/Http/Controllers/Api/V1/Project/ConversationController.php`

Exit criteria:

- Every list endpoint has an explicit pagination policy.
- DB-backed list endpoints no longer paginate in memory unless there is a documented reason.
- Controllers do not manually assemble page and per-page behavior that should live in requests or query services.

## Phase 4 - Move Query Concerns Out Of Controllers

Status:

- Implemented for the Phase 4 review set

Goal:

- Keep controllers orchestration-only and remove inline query composition from the remaining drift points.

Tasks:

- [x] Extract admin user index query logic out of the controller into a repository, read service, or query object.
- [x] Replace raw `Request` usage on list endpoints with dedicated query requests.
- [x] Move inline request parsing and filter switches out of controllers.
- [x] Keep controller actions focused on authorization, invoking one query path, and returning resources.
- [x] Clean up any controller that still combines response composition and query construction in the same method.

Review first:

- `app/Http/Controllers/Api/V1/Admin/UserController.php`
- `app/Http/Controllers/Api/V1/Project/ZoomMeetingController.php`
- `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- `app/Http/Controllers/Api/V1/Project/ActivityController.php`

Exit criteria:

- [x] Controllers no longer own inline filter composition for normal read endpoints.
- [x] List endpoints use dedicated request classes where query input exists.
- [x] Query decisions are easy to unit test without going through controllers.

## Phase 5 - Standardize Filtering And Sorting By Endpoint Family

Status:

- Implemented for the current endpoint families

Goal:

- Align DB-backed list endpoints around one visible filter and sort contract.

Tasks:

- [x] Standardize admin index endpoints around one query grammar.
- [x] Replace direction-only project sorting with explicit sort aliases or field syntax.
- [x] Define allowed filters and allowed sorts per endpoint family, even if the implementation remains custom.
- [x] Move reusable sort and filter behavior into model query builders or clearly named repository methods.
- [x] Separate query logic from human-readable applied filter labels.
- [x] Fix known query metadata inconsistencies while touching the affected code.

Review first:

- `app/Repository/Admin/ProjectFiltersRepository.php`
- `app/Repository/Admin/TaskRepository.php`
- `app/Services/UserNotificationService.php`
- `app/QueryBuilder/ProjectQueryBuilder.php`
- `app/QueryBuilder/TaskQueryBuilder.php`
- `app/Repository/TaskRepository.php`

Known cleanup items in this phase:

- Fixed the duplicated/stale applied-filter sort labeling in the admin project filter slice.
- Stopped mixing field choice and direction semantics across the reviewed user-facing project sort contracts.

Exit criteria:

- [x] Each endpoint family has a documented list of supported filters and sorts.
- [x] Sort defaults are explicit.
- [x] Reusable query behavior is no longer hidden in one-off repository branches.

## Phase 6 - Define The Includes And Eager Loading Policy

Status:

- Implemented for the reviewed high-traffic read endpoints

Goal:

- Make relationship loading intentional instead of endpoint-by-endpoint guesswork.

Tasks:

- Audit every read resource against the relations actually loaded by the query layer.
- Remove eager loads that the paired resource does not use.
- Add named load profiles for common response shapes where that simplifies maintenance.
- Decide which endpoints, if any, should expose public `include` support.
- If `include` is introduced, whitelist includes per endpoint and keep resources `whenLoaded`-friendly.
- If `include` is not introduced yet, document that relationships remain server-controlled.

Review first:

- `app/Http/Resources/Api/V1/Project/ProjectCollectionResource.php`
- `app/Http/Resources/Api/V1/Project/ProjectResource.php`
- `app/Http/Resources/Api/V1/Zoom/MeetingResource.php`
- `app/Http/Resources/Api/V1/ConversationResource.php`
- `app/Services/Dashboard/UserProjectListingService.php`
- `app/Services/Project/ProjectService.php`
- `app/Services/Project/MeetingService.php`

Exit criteria:

- [x] No obvious over-fetching remains in the reviewed high-traffic read endpoints.
- [x] Relationship loading policy is explicit for the current server-controlled cases.
- [x] Resources and query layers agree on what is optional and what is always loaded for the reviewed endpoints.

### Phase 6 Decisions

- Public `include` support is still not introduced for the reviewed endpoints.
- Relationship loading remains server-controlled for project lists, project details, meetings, and conversations.
- User-facing project list responses use a summary load profile of `stage` because `ProjectCollectionResource` serializes stage data but not the owner relation.
- `ProjectResource` uses a detail load profile of `user`, `stage`, `activeMembers`, and `limitedActivities`; `meetings` is not part of the default show response because the resource does not serialize it.
- `MeetingResource` keeps `user` as its current server-controlled response relation.
- `ConversationResource` keeps `user` and `project:id,slug` server-controlled so it can render sender data and project links without broad eager loading.
- Reviewed collection endpoints reject unsupported `include` query parameters explicitly; requestless show actions keep server-controlled loading and do not expose public include support.

## Phase 7 - Standardize The Domain-Specific Exceptions

Status:

- Implemented for the reviewed domain-specific exception slice

Goal:

- Keep the special endpoints special, but make the contract around them intentional.

Tasks:

- Review collection-backed and domain-specific endpoints that do not fit a generic query package.
- Keep custom logic where it adds real product value.
- Push collection filtering down to SQL only when that improves behavior without distorting the domain logic.
- Align parameter names, pagination behavior, and documentation with the shared contract wherever practical.
- Document any endpoint that remains a deliberate exception.

Review first:

- `app/Http/Controllers/Api/V1/Dashboard/DashboardTasksController.php`
- `app/Repository/UserTasksDataRepository.php`
- `app/Http/Controllers/Api/V1/Project/ActivityController.php`
- `app/Services/Project/ProjectActivityQueryFilter.php`
- `app/Http/Controllers/Api/V1/Project/ProjectInsightsController.php`
- `app/Http/Controllers/Api/V1/Dashboard/DashboardChartDataController.php`
- `app/Repository/UserDashboardRepository.php`

Exit criteria:

- [x] Domain-specific endpoints are intentional exceptions, not leftovers.
- [x] Their contracts are documented and consistent with the broader API where possible.

### Phase 7 Decisions

- Dashboard tasks stay intentionally unpaginated because they serve a workload-summary use case rather than an infinite-scroll index.
- Dashboard tasks now accept only canonical nested boolean filters such as `filter[user_created]` and `filter[completed]`; the temporary top-level aliases are removed.
- Project activities stay paginated and keep the canonical `filter[type]` contract; the old top-level `tasks`, `members`, `mine`, and `specifics` aliases are removed.
- Project activity filtering stays SQL-backed through `ProjectActivityQueryFilter`; the old unused collection-based filter was removed as leftover drift.
- Dashboard activities remain an analytics-style date-window exception and continue to use top-level `start_date` and `end_date`.
- Dashboard chart data remains an analytics-style aggregate exception and continues to use top-level `year` and `month`, with `month` requiring `year`.
- Project insights remain a domain-specific exception that uses `sections[]` to select insight groups instead of the generic filter grammar.

## Phase 8 - Spatie Pilot Gate

Goal:

- Move from package availability to evidence-based endpoint adoption.

Tasks:

- After Phases 1 through 7, identify one or two remaining Class A endpoints with the most repetitive request-to-query mapping.
- Only consider a pilot if the candidate endpoint is:
  - DB-backed
  - list-oriented
  - mostly a request-to-builder translation problem
  - not heavily dependent on collection transforms or domain-specific query branches
- Preferred pilot candidates:
  - admin user index
  - meeting index
  - admin task index
- Evaluate the pilot against these criteria:
  - less boilerplate than the house style
  - clearer allowed filters and sorts
  - cleaner include handling if exposed
  - no regression in tests, documentation, or readability
  - no pressure to abandon domain DTOs where they still help

Expected default outcome:

- Keep the existing house style by default.
- Use Spatie selectively only if the pilot produces a clear measurable simplification over the current approach for that endpoint family.

Exit criteria:

- The team makes an explicit yes or no decision for selective package usage.
- If the answer is no, the package question is closed and the repo standard remains internal.
- If the answer is yes for a pilot, the pilot stays limited and does not become an automatic repo-wide rewrite.

### Phase 8 Decisions

- Decision: yes, run a limited pilot.
- Pilot endpoints:
  - `GET /api/v1/admin/users`
  - `GET /api/v1/admin/tasks`
- Why these endpoints: both are DB-backed, paginated, list-oriented, and their filter/sort handling was mostly direct request-to-builder translation.
- Why the other candidates stayed out: meeting index still carries domain-specific branching around scheduled vs previous meetings, notification index is already small enough that the house style stays clearer, and admin project index still benefits from its DTO-backed filter and UI-label path.
- Guardrails kept in place on the pilot:
  - request validation remains the first contract gate
  - unsupported `include`, `fields`, and `append` params are rejected explicitly
  - the pilot does not change the broader default architecture for non-pilot endpoints

## Phase 9 - Tests, Docs, And Enforcement

Status:

- Implemented

Goal:

- Prevent the codebase from drifting back after the refactor.

Tasks:

- [x] Update or add focused tests for each refactored endpoint family.
- [x] Add contract tests where pagination shape, filter names, and sorting behavior are part of the public API.
- [x] Update internal backend guidance with the final query conventions.
- [x] Add a checklist for new list endpoints:
  - dedicated request class
  - explicit filter whitelist
  - explicit sort whitelist or documented fixed sort
  - explicit pagination policy
  - explicit eager load policy
  - resource uses `whenLoaded` for optional relations
- [x] Review changed endpoints for Scramble docs drift after each phase.

### Phase 9 Decisions

- Request validation is now the enforcement layer for unsupported query grammar on reviewed list endpoints.
- If an endpoint does not explicitly opt into `include`, `fields`, or `append`, those parameters must be rejected in the request instead of being ignored downstream.
- Reviewed endpoints now require canonical nested `filter[...]` keys. Top-level filter aliases such as `search`, `status`, `member`, `abandoned`, `user_created`, and the old project-activity aliases are rejected explicitly.
- Legacy scalar `filter=value` shorthands are no longer normalized into nested filters on reviewed endpoints; invalid scalar filter payloads are preserved so validation can fail clearly.
- The only intentional legacy query exceptions still retained are:
  - `GET /api/v1/projects/{project}/tasks?request=archived`
  - `GET /api/v1/projects/{project}/meetings?request=previous`
- New list endpoints should follow this checklist by default:
  - use a dedicated query request
  - validate `filter` as an allowlisted array of supported keys
  - reject top-level aliases for nested filter keys unless the exception is documented
  - define an explicit sort whitelist or document that sorting is fixed/internal
  - define an explicit pagination policy
  - document whether `include`, `fields`, and `append` are rejected or allowlisted
  - keep eager-loading policy explicit and resource-aligned

Exit criteria:

- New list endpoints follow the same conventions by default.
- The final conventions are documented in one place.
- Query drift is caught by tests and review checklists instead of later cleanup plans.

## Recommended Execution Order

Use this sequence if you want the quickest path to visible improvement without broad churn.

1. Finish Phase 1 and lock the public query contract.
2. Build the shared query request primitives from Phase 2.
3. Refactor user projects pagination and meeting index request handling from Phase 3.
4. Extract admin user index query logic from the controller in Phase 4.
5. Standardize admin project, admin task, and notifications filtering and sorting in Phase 5.
6. Run the eager-loading audit in Phase 6.
7. Review the collection-backed exceptions in Phase 7.
8. Decide whether a small Spatie pilot is still worth running in Phase 8.
9. Lock the result down with Phase 9 documentation and tests.

## Definition Of Done

This plan is complete when all of the following are true:

- Filter names follow one documented contract across list endpoints.
- Sort behavior is explicit and safely whitelisted wherever public sorting is supported.
- Includes are either server-controlled by policy or client-controlled through a whitelist.
- Paginated endpoints share one response shape and one validation policy.
- Controllers no longer own query logic that belongs deeper in the stack.
- Collection-backed exceptions are documented rather than accidental.
- The team has made and recorded a final evidence-based decision on selective Spatie usage.
