## Plan: Handler Refactor Roadmap

Refactor the API exception flow in small, test-backed phases without changing the canonical error contract or forcing a Laravel 12 bootstrap migration. Keep the current handler entry point, remove business-state detection from it, move app-specific response metadata onto exception classes, and leave only framework exception mapping plus the shared JSON envelope in the handler.

**Steps**

1. Phase 0: freeze current behavior before refactoring. Tighten the focused tests around the canonical `{message, code, errors, meta}` payload, the API-only `renderViaCallbacks` gate, the archived project/task `409 conflict` responses, and the current Zoom/Paddle mappings. This blocks every later step.

2. Phase 1: remove archived resource discovery from the handler. Introduce an explicit archived-resource exception and move the throw site upstream, preferably into middleware or a resolver that runs after route model binding on `.withTrashed()` endpoints. The handler should only serialize that exception to the existing `project_archived` and `task_archived` payloads. Depends on step 1.

3. Phase 2: introduce a shared app API exception abstraction. Add one app-level base exception or contract that exposes public message, status, API code, optional errors, optional meta, and reportability intent. Migrate app-authored exceptions first: subscription exceptions, plan limit exception, archived resource exception, external service exceptions, Paddle exceptions, and Zoom exceptions. Keep framework exceptions mapped centrally in the handler. Depends on step 2.

4. Phase 3: thin the handler to framework concerns plus one app-exception renderer. Replace the many app-specific `renderable` closures with one renderable for the shared app exception type. Keep a small number of framework-specific handlers in the handler: `ValidationException`, `AuthenticationException`, `AuthorizationException`, `MethodNotAllowedHttpException`, generic `HttpException`, generic `Throwable`, and a final `NotFoundHttpException` fallback if needed. Remove the empty no-op `reportable` closure. Depends on step 3.

5. Phase 4: move reporting policy closer to exception classes. Convert app exceptions that should not be reported to implement `ShouldntReport` instead of expanding `Handler::$dontReport`. That should include business and user-caused exceptions such as subscription-required, plan-limit, subscription conflict, and Zoom user errors. Depends on step 3 and can overlap with the tail of step 4 once the shared exception abstraction exists.

6. Phase 5: extract shared payload and message helpers if the handler still feels dense. Move `apiErrorResponse`, public-message filtering, and default status/code lookup into a dedicated formatter or support class only after the bigger structural cleanup is done. Depends on step 4.

7. Phase 6: treat Laravel 12 bootstrap modernization as a separate follow-up. Do not mix structural cleanup with migrating exception registration into the newer bootstrap style. Your upgraded-from-Laravel-10 structure is still valid on Laravel 12. Revisit `withExceptions(...)` or `shouldRenderJsonWhen(...)` only after the handler has been simplified and behavior is stable.

**Relevant files**

- Handler.php — current exception router, API gate, payload builder, status/code lookup, and archived resource detection.
- app.php — current Laravel 10-style exception handler binding that remains valid on Laravel 12.
- PlanLimitExceededException.php — existing example of a rich app exception that already carries metadata.
- SubscriptionRequiredException.php — existing app exception that should likely own more of its rendering and reporting policy.
- ExternalServiceUnavailableException.php — current integration exception carrying status metadata.
- PaddleException.php — current provider-specific external-service base exception.
- ZoomException.php — current provider exception base that can be upgraded to carry API metadata.
- v1.php — `.withTrashed()` routes and the best place to identify where archived-resource checks should run.
- ApiExceptionHandlerTest.php — contract tests for the canonical API payload and exception mappings.
- HandlerReportingTest.php — tests for reportability policy.
- ProjectFeatureTest.php — archived project behavior tests.
- TaskTest.php — archived task behavior tests.
- New app exception base or contract under Exceptions.
- New archived-resource exception under Exceptions.
- New archived-resource detector under Middleware or `app/Services/Api/V1`.

**Verification**

1. Before any refactor, run `php artisan test --compact ApiExceptionHandlerTest.php tests/Feature/Exceptions/HandlerReportingTest.php`.
2. Lock archived-resource behavior with `php artisan test --compact ProjectFeatureTest.php --filter=trashed_project_activity_request_returns_project_not_active_message` and `php artisan test --compact TaskTest.php --filter=trashed_task_activity_request_returns_task_not_active_message`.
3. After introducing the archived-resource exception throw site, add or update the narrowest tests covering that throw path before touching the shared handler logic again.
4. After moving app exceptions to a shared abstraction, rerun the exception contract tests plus the specific provider tests affected by Zoom or Paddle mappings.
5. Run `vendor/bin/pint --dirty` after each implementation slice that changes PHP files.
6. After the refactor is stable, run a broader exception-focused regression pass, then decide whether a full application suite is needed.

**Decisions**

- Keep the existing handler binding in app.php during this refactor; do not migrate bootstrap exception configuration in the same workstream.
- Preserve the current API error contract exactly.
- Preserve current public-message rules, including no leaking of raw 5xx internals.
- Preserve current archived project/task semantics: `409 conflict` with stable machine-readable codes.
- Remove direct model and database lookups from the handler.
- Keep ordering-sensitive framework mappings documented until the shared app exception renderer replaces them.

**Further Considerations**

1. Recommended archived-resource design: start with one shared archived-resource exception carrying `resourceType` and optional identifiers; split into separate project/task exceptions only if payloads or metadata diverge later.
2. Recommended throw-site design: prefer middleware or a dedicated resolver over controller-level checks so archived-resource behavior stays centralized and reusable.
3. Recommended stopping point: after phase 4, reassess. Phase 5 and phase 6 are cleanup, not prerequisites for maintainability.

4. If you want, I can revise this into a shorter execution checklist for a single PR at a time.
5. If you want, I can split phase 1 into exact file-by-file implementation tasks next.
