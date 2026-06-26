# Code Review — Subscription & Plan Limit Commits

**Commits reviewed:** `ba2244d5..4f4c4ec9` (6 commits)
**Scope:** Subscription lifecycle, plan limit enforcement, billing status exposure, production guards

---

## 🔴 HIGH Priority

### 1. `ActiveTasksPerProject` count query now counts ALL tasks — limit semantics broken

In [PlanLimitType.php](file:///c:/Users/Hamza/daywright/app/Enums/Subscription/PlanLimitType.php#L158-L159), the count loader changed from:

```diff
-'tasks as active_tasks_count' => static fn (Builder $query): Builder => $query->whereIn('status_id', TaskStatus::active()),
+'tasks as active_tasks_count' => static fn (Builder $query): Builder => $query,
```

The config key is still named `max_active_tasks_per_project` and the attribute is still `active_tasks_count`, but it now counts **every task** (including completed, archived, trashed). The label was renamed from "Active tasks" to "Tasks", which suggests this was intentional — but the config key and attribute name still say "active", creating confusion.

**Impact:** If the plan limit config value was calibrated for active tasks only, the limit will fire much sooner in production because completed/trashed tasks inflate the count. Also, [RestoreTaskAction.php](file:///c:/Users/Hamza/daywright/app/Actions/Task/RestoreTaskAction.php#L26-L29) now runs this check — restoring a trashed task would count itself (already in total) before restore completes, which means the limit check may be off-by-one depending on whether soft-deleted tasks are included in the `tasks` relationship default scope.

**Recommendation:**

- If you want total tasks: rename the config key, the attribute, and the enum case for clarity.
- Verify that the `tasks` relationship's default scope excludes soft-deleted records (SoftDeletes does this by default, so restoring should be OK), but confirm with an integration test.

---

## ✅ What Looks Good

- **Row-level locking** with `lockForUpdate()` + transaction retries is solid concurrency control
- **`subscriptionName()` centralization** — removing hardcoded `'DayWright'` strings is a good consistency win
- **Unique constraint migration** with preflight duplicate check is production-safe
- **`AcceptProjectInvitationAction`** refactor to use `PlanLimitService::executeWithinProjectLimit()` is clean and properly delegates locking
- **Rich exception context** via `context()` for internal logging is good observability practice
- **`billing_status` exposure** in the API response is useful for frontend state machines
