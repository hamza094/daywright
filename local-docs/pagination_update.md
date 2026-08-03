## Cursor Pagination Migration Plan

Migrate three features from offset-based paginate() to cursor-based cursorPaginate(), updating backend, frontend, and tests for each.

Note: The plan must follows my .ai/guidelines/backend-guidlines.md and .ai/vue-ai-guidlines.md

## Key Architectural Decisions

### IMPORTANT

Cursor pagination JSON shape differs from offset pagination. Laravel's CursorPaginator returns next_cursor / prev_cursor strings (instead of current_page / last_page / total). The frontend must switch from page numbers to opaque cursor strings. There is no total count in cursor pagination — this is by design.

### IMPORTANT

ApiController::respondWithPaginatedData currently type-hints LengthAwarePaginator. We will add a new respondWithCursorPaginatedData helper that accepts CursorPaginator and returns the cursor-specific meta shape, or we can leverage Laravel's native ResourceCollection::response() which already handles cursor paginators correctly (the same pattern ConversationController@index and NotificationsController@index use today).

### IMPORTANT

Dashboard Tasks currently returns a Collection (no pagination at all). This feature will need a query-level change from ->get() to ->cursorPaginate().

## Shared Preparation (Do First)

Backend: Add cursor pagination support to ApiController

[MODIFY]
ApiController.php
Add a respondWithCursorPaginatedData() method that accepts CursorPaginator and returns:
json

{
"data": [...],
"meta": { "per_page": 10, "next_cursor": "abc...", "prev_cursor": "xyz..." },
"links": { "next": "...?cursor=abc", "prev": "...?cursor=xyz" }
}
Import Illuminate\Pagination\CursorPaginator.
This keeps the existing respondWithPaginatedData intact for any remaining offset-paginated endpoints.

Backend: Add cursorRule() to the pagination trait

[MODIFY]

InteractsWithApiQueryPagination.php
Add a cursorRule() method returning ['sometimes', 'string'].
Add a cursorValue() accessor: return $this->validated('cursor', null).
Keep existing pageRule() / pageNumber() for non-migrated endpoints.
Frontend: Add cursor-aware pagination utilities

[MODIFY]

apiResponse.js

Add a getCursorPaginatedData(response) export that normalizes the cursor response shape:
js

{ data: [...], meta: { next_cursor, prev_cursor, per_page }, links: { next, prev } }
Keep getPaginatedData for any remaining offset-paginated pages.

## Feature 1: Project Conversations (Infinite Scroll)

Step 1.1 — Backend: Repository
[MODIFY]
ConversationRepository.php
Current After
Returns LengthAwarePaginator Returns CursorPaginator
->paginate($perPage, ['*'], 'page', $page)	->cursorPaginate($perPage)

Accepts int $page param Drop $page param (cursor auto-read from request)

Change import from LengthAwarePaginator to CursorPaginator.

Change method signature: getProjectConversations(Project $project, int $perPage): CursorPaginator.

Replace ->paginate(...) with ->cursorPaginate($perPage).
Change ->orderBy('id') to ->orderByDesc('id') (loading newest first).

Remove ->withQueryString() (cursor pagination handles this natively).

## Step 1.2 — Backend: Form Request

[MODIFY]
ConversationIndexRequest.php
Replace 'page' => $this->pageRule() with 'cursor' => $this->cursorRule().
Update supportedTopLevelQueryParameters() to ['cursor', 'per_page'].
Remove pageNumber() usage (no longer needed).

## Step 1.3 — Backend: Controller

[MODIFY]
ConversationController.php
Update index() to call $repository->getProjectConversations($project, $request->perPage()) (remove $request->pageNumber()).
The response already uses ConversationResource::collection($paginator)->response(), which will work with CursorPaginator out of the box — Laravel handles the JSON shape automatically.

## Step 1.4 — Backend: Tests

[MODIFY]
ConversationTest.php
Update existing tests:

allowed_user_can_see_project_conversations — Adjust assertJsonStructure from ['data', 'links', 'meta'] to check for cursor-specific keys: meta.next_cursor, meta.prev_cursor, meta.per_page, links.next, links.prev. Remove meta.total assertion.

conversation_index_can_limit_results_per_page — Remove assertJsonPath('meta.total', 4) (no total in cursor pagination). Assert meta.per_page is 2, assert data count is 2, and assert meta.next_cursor is not null (since there are more items).

conversation_index_returns_empty_paginated_payload — Update structure assertions for cursor shape.

conversation_index_rejects_unsupported_top_level_query_parameters — Remains the same.

Add new test:

conversation_index_supports_cursor_pagination — Create 4 conversations. Fetch first page with per_page=2. Assert 2 items returned + meta.next_cursor present. Fetch next page using the returned next_cursor. Assert next 2 items returned.

## Step 1.5 — Frontend: Chat.vue (Infinite Scroll)

[MODIFY]
Chat.vue
Data changes:

Replace conversations: { ...EMPTY_PAGINATED_CONVERSATIONS } with a cursor-aware structure:
js

conversations: { data: [], meta: { next_cursor: null, prev_cursor: null }, links: {} }
Add loadingMore: false flag.
loadConversations() changes:

Use getCursorPaginatedData(response) instead of getPaginatedData(response).
Store the full cursor response.
Add loadMoreConversations() method:

If this.conversations.meta.next_cursor is null, return early.
Set loadingMore = true.
Call axios.get('/projects/' + this.slug + '/conversations', { params: { cursor: this.conversations.meta.next_cursor } }).
Prepend the older messages to the beginning of this.conversations.data (since we're scrolling up for older content).
Update meta with the new cursor values.
Add infinite scroll observer:

In mounted(), set up an IntersectionObserver on a sentinel element placed at the top of the chat panel (.chat-panel).
When the sentinel becomes visible, call loadMoreConversations().
Clean up the observer in beforeDestroy().
Template changes:

Add a sentinel <div ref="scrollSentinel"> at the top of the <ul class="chat"> list.
Add a loading indicator when loadingMore is true.

### Feature 2: Dashboard Tasks Data (Load More Button)

## Step 2.1 — Backend: Repository

[MODIFY]
UserTasksDataRepository.php
Current After
Returns Collection (via ->get()) Returns CursorPaginator (via ->cursorPaginate())
Change import from Collection to CursorPaginator.
Change return type from Collection to CursorPaginator.
Add int $perPage = 15 parameter.
Replace ->get() with ->orderBy('id')->cursorPaginate($perPage).

## Step 2.2 — Backend: Form Request

[MODIFY]
UserTasksRequest.php
Add 'cursor' => $this->cursorRule() and 'per_page' => $this->perPageRule() to the validation rules.
Update supportedTopLevelQueryParameters() to include 'cursor' and 'per_page'.
Add perPage() accessor returning $this->perPageValue(15).

## Step 2.3 — Backend: Controller

[MODIFY]
DashboardTasksController.php
Update \_\_invoke() to pass $request->perPage() to $repository->getTasks().
Replace $this->respondWithData(...) with cursor-paginated response:
php

return $this->respondWithCursorPaginatedData(
    UserTasksResource::collection($tasks),
$tasks,
    meta: [
        'applied_filters' => UserTasksResource::appliedFilters($filters),
],
);
Update the Scramble response type annotation to reflect cursor meta shape (remove total, add next_cursor, prev_cursor).
Note: $tasks->count() is no longer meaningful as a total. The meta.total key will be removed.

## Step 2.4 — Backend: Tests

[MODIFY]
UserTasksDataTest.php
Update existing tests:

All tests asserting meta.total — remove those assertions (cursor pagination has no total).

All tests asserting assertCount(N, $responseData['data']) — keep as-is (count of items per page is still valid).

Update assertJsonStructure to expect meta.next_cursor, meta.prev_cursor, meta.per_page instead of meta.total.

Tests referencing $responseData['data'][3] (positional access) should be updated since default per_page=15 will still return all items in tests with < 15 records — but double-check ordering.
Add new tests:

dashboard_tasks_support_cursor_pagination — Create 4 tasks. Fetch with per_page=2. Assert 2 items + next_cursor not null. Fetch next page with cursor. Assert next 2 items.

dashboard_tasks_accept_per_page_parameter — Fetch with per_page=5. Assert meta.per_page is 5.

## Step 2.5 — Frontend: TasksData.vue (Load More Button)

[MODIFY]
TasksData.vue
Data changes:

Add nextCursor: null and loadingMore: false.
loadTasks() changes (full reset on filter change):

On filter change, reset userTasks = [], nextCursor = null.
Parse response with getCursorPaginatedData(response).
Set this.userTasks = result.data.
Set this.nextCursor = result.meta.next_cursor.
Read appliedFilters from result.meta.applied_filters.
Remove totalTasks (no total available with cursors).
Add loadMoreTasks() method:

If nextCursor is null, return.
Set loadingMore = true.
Call API with { params: { ...filterParams, cursor: this.nextCursor } }.
Append results: this.userTasks.push(...result.data).
Update this.nextCursor = result.meta.next_cursor.
[MODIFY]
dashboardResponse.js
Update readDashboardTasks() to use getCursorPaginatedData() and extract cursor metadata.
Remove total from the return shape (or keep it as data.length for display).
Template changes:

Replace totalTasks badge with userTasks.length tasks loaded (or remove the count).
Add a "Load More" button at the bottom of the task list:
html

<button v-if="nextCursor" @click="loadMoreTasks" :disabled="loadingMore"
class="btn btn-outline-primary btn-sm btn-block mt-2">
{{ loadingMore ? 'Loading...' : 'Load More' }}
</button>

### Feature 3: Notifications (Load More Button)

Step 3.0 — Backend: Database Migration (Index)
Cursor pagination relies on an ordered column, and for Notifications we use latest() which orders by created_at. We need to add an index on created_at to the notifications table for optimal performance. Note that conversations and tasks order by id (primary key), which is already indexed by default, so no extra migrations are needed for those.

Create a new migration: php artisan make:migration add_created_at_index_to_notifications_table
In the up() method, add $table->index('created_at'); to the notifications table.
In the down() method, add $table->dropIndex(['created_at']);

## Step 3.1 — Backend: Repository

[MODIFY]
NotificationRepository.php
Current After
Returns LengthAwarePaginator via ->paginate() Returns CursorPaginator via ->cursorPaginate()
Change import from LengthAwarePaginator to CursorPaginator.
Change return type to CursorPaginator.
Replace ->paginate($perPage) with ->cursorPaginate($perPage).
Note: The query already has ->latest() which provides the required orderBy clause for cursor pagination.

## Step 3.2 — Backend: Service

[MODIFY]
UserNotificationService.php
Change return type of paginateForUser() from LengthAwarePaginator to CursorPaginator.
Update import.

## Step 3.3 — Backend: Form Request

[MODIFY]
NotificationIndexRequest.php
Replace 'page' => $this->pageRule() with 'cursor' => $this->cursorRule().
Update supportedTopLevelQueryParameters(): replace 'page' with 'cursor'.
Step 3.4 — Backend: Controller
[MODIFY] 
NotificationsController.php
The index() method already uses NotificationResource::collection($paginator)->response(). This will automatically serialize cursor pagination correctly — no code change needed beyond what the service/repo return.
Update the #[ScrambleResponse] type annotation to reflect cursor meta shape.

## Step 3.5 — Backend: Tests

[MODIFY]
UserNotificationInboxTest.php
Update existing tests:

auth_user_can_fetch_there_notifications — Update assertJsonStructure to expect cursor-shape keys (meta.next_cursor, meta.prev_cursor, meta.per_page, links.next, links.prev).
auth_user_gets_paginated_empty_notifications_shape — Same structure update.
Add new test:

notification_index_supports_cursor_pagination — Create multiple notifications. Fetch with per_page=1. Assert 1 item + meta.next_cursor present. Fetch next page using cursor. Assert next item returned.
NOTE

NotificationDeliveryTest.php
tests notification sending (not listing), so it requires no changes.

## Step 3.6 — Frontend: Vuex Store

[MODIFY]
notifications.js
State changes:

Change initial state shape to cursor-aware:
js

const EMPTY_CURSOR_RESPONSE = {
data: [],
meta: { next_cursor: null, prev_cursor: null, per_page: 25 },
links: {},
};
Action changes:

fetchNotificationsFromApi — Use getCursorPaginatedData() instead of getPaginatedData(). Replace page parameter with cursor.
getAllNotifications — Accept { filter, cursor } instead of { filter, page }.
fetchNotifications — Same cursor pattern.
Add loadMoreNotifications action:

Read state.allNotifications.meta.next_cursor.
If null, return.
Fetch the next page using the cursor.
Commit a new appendAllNotifications mutation that appends data and updates meta/links.
Add appendAllNotifications mutation:

js

appendAllNotifications(state, payload) {
state.allNotifications = {
data: [...state.allNotifications.data, ...payload.data],
meta: payload.meta,
links: payload.links,
};
}
[MODIFY]
notificationQuery.js
Replace page parameter logic with cursor parameter:
js

if (typeof cursor === 'string' && cursor.trim() !== '') {
params.cursor = cursor;
}

## Step 3.7 — Frontend: UserNotification.vue (Load More Button)

[MODIFY]
UserNotification.vue
Template changes:

Remove <pagination :data="notifications" @pagination-change-page="getResults">.
Add a "Load More" button:
html

<div v-if="hasMoreNotifications" class="text-center mt-3 mb-3">
  <button @click="loadMore" :disabled="loadingMore" class="btn btn-outline-primary btn-sm">
    {{ loadingMore ? 'Loading...' : 'Load More' }}
  </button>
</div>
Script changes:

Add loadingMore: false to data().

Add computed hasMoreNotifications → this.notifications.meta?.next_cursor != null.

Replace getResults(page) with getResults() (no page number).
Update created() to call this.getResults().

Add loadMore() method that dispatches loadMoreNotifications.
Update filterNotifications(type) to reset and re-fetch (dispatches getAllNotifications with no cursor).

Verification Plan
Automated Tests
Run all updated test suites after each feature is implemented:

bash

# Feature 1

php artisan test --filter=ConversationTest

# Feature 2

php artisan test --filter=UserTasksDataTest

# Feature 3

php artisan test --filter=UserNotificationInboxTest
php artisan test --filter=NotificationDeliveryTest

# Full suite

php artisan test
Manual Verification

Conversations: Open a project chat panel. Scroll to the top — older messages should load automatically. New messages via broadcast should still appear at the bottom.

Dashboard Tasks: Load dashboard. Apply filters. Click "Load More" — additional tasks should append below. Changing a filter should reset the list.

Notifications: Open notifications page. Click "Load More" — additional notifications should append below. Filter toggle (All/Unread) should reset the list. Mark as read/unread/delete should still work on the loaded items.

Implementation Order
Phase Feature Estimated Files
0 Shared preparation (ApiController, pagination trait, apiResponse.js) 3
1 Project Conversations 5 (repo, request, controller, test, Chat.vue)
2 Dashboard Tasks 5 (repo, request, controller, test, TasksData.vue + dashboardResponse.js)
3 Notifications 7 (repo, service, request, controller, test, store, UserNotification.vue + notificationQuery.js)
