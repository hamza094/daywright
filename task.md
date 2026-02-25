Your task is to resolve these issues in admin panel releated section one by one fix it review than move to next one add or update test if its critical
if backend changes reflect on frontend then also make sure the frontend align with backend

1 - `UsersResource` has an N+1 query\*\*

In UsersResource.php:

```php
'projects_member' => $this->members(true)->count(),
```

This fires a new query for every user in the paginated result. Eager load the relationship count in `UserController::index()` instead.

2 - `StageController::update` skips uniqueness

`store()` correctly uses `StageRequest` (which enforces uniqueness), but `update()` uses inline `$request->validate(['name' => 'required|string|max:255'])` — no uniqueness rule on the updated name, so two stages can end up with the same name.

3 - `maxStatusCount` blocks status updates

`TaskStatusRequest` applies the `maxStatusCount` rule on both `POST` and `PUT/PATCH` requests. If you're at the count limit, updating an existing status's color/label will be incorrectly blocked.

4 - `PaddleController` exception handling commented out

The try/catch block is commented out:

```php
// try {
$data = $paddle->SubscriptionUsersList(...);
// } catch ...
```

A Paddle API failure will throw an unhandled exception, leaking a stack trace to the caller.

5 - `ProjectFiltersRepository` uses `get()` but controller paginates

In ProjectFiltersRepository.php the query ends with `->get()`, returning an `Illuminate\Support\Collection`. The controller then calls `ProjectResource::collection($projects)->paginate($perPage)` — calling `paginate()` on a `ResourceCollection` wrapping a plain Collection is not a real Laravel method and will silently return wrong data or throw at runtime, especially on large datasets.

6 - Tasks search `v-model` missing — search broken

In Tasks.vue, the search `<input>` uses `@keydown="searchTasks()"` but no `v-model`. The `searchTerm` data property is never populated from the input, so the search filter is effectively broken.

7 - `maxStatusCount` class name violates PSR

The Rule class `maxStatusCount` starts with a lowercase letter, which violates PSR-4 autoloading conventions and the project's own naming guidelines. It should be `MaxStatusCount`.
