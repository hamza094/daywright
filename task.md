fix these issues releted to admin section one by one add or update any releated tests if its needed after make updates check its refrences correctly in frontend and backend

1- `ProjectFiltersRepository::applyStatusFilter` may still have issues\*\*

The recent fix changed `->filter()` to `->where()`, but the callback signature and logic need verification against the actual `status` column values and query builder state.

2 - `DashboardController` has no error handling\*\*

If `DashboardRepository` queries fail, the controller returns a raw 500. Wrap in try/catch or add a global admin exception handler.

3 - `TaskRepository` — verify pagination and search\*\*

Similar to the `ProjectFiltersRepository` fix, ensure `TaskRepository` returns paginated results (not `->get()`) and search input is sanitized.

4 - Frontend admin components have no loading/error states for some views\*\*

`Projects.vue`, `Tasks.vue`, `Dashboard.vue` — verify they handle API errors gracefully (show toast or fallback UI, not a blank screen).

5 - Add `FormRequest` for `ProjectController` bulk operations input validation (currently uses `ProjectBulkDeleteRequest` but verify search/filter params).

6 - Add `index` return type to `UserController::index()` (`AnonymousResourceCollection`).
