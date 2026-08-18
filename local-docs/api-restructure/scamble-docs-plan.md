# Scramble OpenAPI Documentation — Fix Plan

## Background

The Scramble setup (`ScrambleServiceProvider`) is already excellent. The issues are concentrated in the **controllers** themselves, not the service provider. All fixes are isolated, low-risk, and do not touch business logic.

---

## Phase 1: Code Correctness Fixes (Critical)

These are real bugs — wrong HTTP status codes and inconsistent return types that affect both runtime behavior and Scramble's ability to infer the correct response shape.

> [!CAUTION]
> Fix these first. They are guideline violations and will produce incorrect OpenAPI specs.

### Files to Modify

#### [MODIFY] `ProjectController.php` — `destroy()` returns 200 instead of 204

The `destroy` method currently returns a `200` message response. Per backend guidelines, all `DELETE` endpoints must return `204 No Content`.

```diff
- return $this->respondWithMessage($project->name.' abandoned successfully');
+ return $this->respondNoContent();
```

#### [MODIFY] `TaskController.php` — `show()` returns `TaskResource` instead of `JsonResponse`

The `show` method bypasses the `respondWithData` wrapper, inconsistent with every other method in the project.

```diff
- public function show(Project $project, Task $task): TaskResource
- {
-     $task->loadMissing(['project:id,slug', 'status', 'assignee']);
-     return new TaskResource($task);
- }
+ public function show(Project $project, Task $task): JsonResponse
+ {
+     $task->loadMissing(['project:id,slug', 'status', 'assignee']);
+     return $this->respondWithData(new TaskResource($task));
+ }
```

#### [MODIFY] `TaskController.php` — `destroy()` also returns a message instead of 204

Same issue as `ProjectController::destroy()`.

```diff
- return $this->respondWithMessage('Task deleted successfully.');
+ return $this->respondNoContent();
```

### Verification

After changes, run PHPStan: `vendor/bin/phpstan analyze`

---

## Phase 3: Update Backend Guidelines

Update [`backend-guidelines.md`](file:///c:/Users/Hamza/daywright/.ai/guidelines/backend-guidelines.md) with an explicit API Documentation section so future controllers are written correctly the first time.

> [!NOTE]
> This is a documentation-only change. No code is modified.

### Rules to Add (new Section 24)

```markdown
## 24. API Documentation (Scramble)

- ✅ Public-facing controller methods that will be used by external developers or called from generated SDKs SHOULD have a `#[Endpoint(operationId: 'resource.action')]` attribute (e.g., `projects.list`, `tasks.create`).
- ✅ Tags are automatically resolved by `ScrambleServiceProvider::resolvePublicApiTag()` based on URI patterns. Do NOT manually declare `@tags`.
- ✅ `DELETE` endpoints MUST use `respondNoContent()` (204). Never return a message body on delete.
- ✅ All controller method return types MUST be `JsonResponse`. Never return a plain `Resource` or `ResourceCollection` directly.
- ❌ Never use `@unauthenticated` — the Scramble route filter handles auth exclusion automatically by filtering `session.auth` and `firstParty.auth` routes.
- ❌ Never manually declare `@throws` for 401/403/404/422/429/500 — these are injected globally by `applySharedPublicApiErrorResponses()` in the ScrambleServiceProvider.
```

---

## Execution Order

|| Phase | Effort | Risk | Priority |
|| :--- | :--- | :--- | :--- |
|| **Phase 1** — Code fixes (3 targeted changes) | ~15 min | Low | 🔴 Do first |
|| **Phase 2** — Add `#[Endpoint(operationId)]` attributes (public-facing endpoints) | ~1-2 hrs | None | 🟡 Do second |
|| **Phase 3** — Update backend guidelines | ~20 min | None | 🟢 Do last |
