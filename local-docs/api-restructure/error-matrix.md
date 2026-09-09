# Runtime-to-Documentation Error Matrix

Generated during Phase 1 of the Public API Error Documentation Production Plan.

## Purpose

Map all error code sources to understand duplication and ownership before creating a unified catalog.

## Sources

1. **ApiErrorFormatter** (`app/Exceptions/Support/ApiErrorFormatter.php`)
2. **HandlesApiExceptions** (`app/Exceptions/Traits/HandlesApiExceptions.php`)
3. **ApiException subclasses** (`app/Exceptions/`)
4. **ScrambleServiceProvider** (`app/Providers/ScrambleServiceProvider.php`)

---

## Error Code Inventory

### Standard HTTP Status Codes (ApiErrorFormatter)

| Status | Code                    | Message                                                | Source                                      |
| ------ | ----------------------- | ------------------------------------------------------ | ------------------------------------------- |
| 400    | `bad_request`           | The request could not be processed.                    | `ApiErrorFormatter::defaultCodeForStatus()` |
| 401    | `unauthenticated`       | Authentication is required.                            | `ApiErrorFormatter::defaultCodeForStatus()` |
| 403    | `forbidden`             | You are not authorized to perform this action.         | `ApiErrorFormatter::defaultCodeForStatus()` |
| 404    | `not_found`             | Resource not found.                                    | `ApiErrorFormatter::defaultCodeForStatus()` |
| 405    | `method_not_allowed`    | Method not allowed.                                    | `ApiErrorFormatter::defaultCodeForStatus()` |
| 409    | `conflict`              | The request conflicts with the current resource state. | `ApiErrorFormatter::defaultCodeForStatus()` |
| 422    | `validation_error`      | Validation failed.                                     | `ApiErrorFormatter::defaultCodeForStatus()` |
| 429    | `rate_limited`          | Too many requests. Please try again later.             | `ApiErrorFormatter::defaultCodeForStatus()` |
| 500    | `internal_server_error` | An unexpected server error occurred.                   | `ApiErrorFormatter::defaultCodeForStatus()` |
| 503    | `service_unavailable`   | The service is temporarily unavailable.                | `ApiErrorFormatter::defaultCodeForStatus()` |

### Infrastructure Exceptions (HandlesApiExceptions)

| Exception                         | Status  | Code                       | Meta                  | Source                                                 |
| --------------------------------- | ------- | -------------------------- | --------------------- | ------------------------------------------------------ |
| `InvalidStateTransitionException` | 422     | `invalid_state_transition` | From exception        | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `ModelNotFoundException`          | 404     | `not_found`                | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `NotFoundHttpException`           | 404     | `not_found`                | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `AuthenticationException`         | 401     | `unauthenticated`          | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `AuthorizationException`          | 403     | `forbidden`                | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `MethodNotAllowedHttpException`   | 405     | `method_not_allowed`       | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `ThrottleRequestsException`       | 429     | `rate_limited`             | `retry_after_seconds` | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `HttpException`                   | Dynamic | `defaultCodeForStatus()`   | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `ValidationException`             | 422     | `validation_error`         | Field errors          | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `RateLimitReachedException`       | 429     | `rate_limited`             | `retry_after_seconds` | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `S3Exception`                     | 500     | `storage_error`            | `provider: s3`        | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `QueryException`                  | 500     | `database_error`           | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |
| `Throwable` (fallback)            | 500     | `internal_server_error`    | None                  | `HandlesApiExceptions::registerApiExceptionHandlers()` |

### Business Exception Codes (ApiException Subclasses)

| Exception                              | Status                | Code                      | Meta                                                                                                                    | Source                                             |
| -------------------------------------- | --------------------- | ------------------------- | ----------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| `ArchivedResourceException::project()` | 409                   | `project_archived`        | None                                                                                                                    | `ArchivedResourceException::errorCode()`           |
| `ArchivedResourceException::task()`    | 409                   | `task_archived`           | None                                                                                                                    | `ArchivedResourceException::errorCode()`           |
| `PlanLimitExceededException`           | 403                   | `plan_limit_exceeded`     | `reason`, `limit_type`, `limit_label`, `current_usage`, `max_allowed`, `limit_scope`, `can_upgrade`, `upgrade_required` | `PlanLimitExceededException::meta()`               |
| `SubscriptionRequiredException`        | 403                   | `subscription_required`   | `upgrade_required`                                                                                                      | `SubscriptionRequiredException::meta()`            |
| `TaskNotTrashedException`              | 403                   | `task_not_trashed`        | None                                                                                                                    | `TaskNotTrashedException::errorCode()`             |
| `DashboardServiceException`            | Dynamic (default 500) | `dashboard_service_error` | None                                                                                                                    | `DashboardServiceException::errorCode()`           |
| `ExternalServiceUnavailableException`  | Dynamic (default 500) | `defaultCodeForStatus()`  | None                                                                                                                    | `ExternalServiceUnavailableException::errorCode()` |

### Integration Exception Codes (Not Public)

| Exception                           | Status | Code | Public? | Source             |
| ----------------------------------- | ------ | ---- | ------- | ------------------ |
| `Zoom\NotFoundException`            | -      | -    | No      | Zoom integration   |
| `Zoom\UnauthorizedException`        | -      | -    | No      | Zoom integration   |
| `Zoom\ZoomException`                | -      | -    | No      | Zoom integration   |
| `Zoom\ZoomExternalFailureException` | -      | -    | No      | Zoom integration   |
| `Zoom\ZoomUserErrorException`       | -      | -    | No      | Zoom integration   |
| `Paddle\PaddleException`            | -      | -    | No      | Paddle integration |
| `Paddle\PaddleRequestException`     | -      | -    | No      | Paddle integration |
| `Paddle\SubscriptionException`      | -      | -    | No      | Paddle integration |
| `UnableToResolveUserIdException`    | -      | -    | No      | Internal           |

---

## ScrambleServiceProvider Duplication

The `ScrambleServiceProvider::publicApiErrorResponseDefinitions()` method duplicates the following:

| Response                        | Schema                                  | Status | Message                                                | Code                    | Meta                        |
| ------------------------------- | --------------------------------------- | ------ | ------------------------------------------------------ | ----------------------- | --------------------------- |
| `PublicBadRequestError`         | `PublicBadRequestErrorEnvelope`         | 400    | The request could not be processed.                    | `bad_request`           | []                          |
| `PublicUnauthenticatedError`    | `PublicUnauthenticatedErrorEnvelope`    | 401    | Authentication is required.                            | `unauthenticated`       | []                          |
| `PublicForbiddenError`          | `PublicForbiddenErrorEnvelope`          | 403    | You are not authorized to perform this action.         | `forbidden`             | []                          |
| `PublicNotFoundError`           | `PublicNotFoundErrorEnvelope`           | 404    | Resource not found.                                    | `not_found`             | []                          |
| `PublicMethodNotAllowedError`   | `PublicMethodNotAllowedErrorEnvelope`   | 405    | Method not allowed.                                    | `method_not_allowed`    | []                          |
| `PublicConflictError`           | `PublicConflictErrorEnvelope`           | 409    | The request conflicts with the current resource state. | `conflict`              | []                          |
| `PublicValidationError`         | `PublicApiValidationErrorEnvelope`      | 422    | Validation failed.                                     | `validation_error`      | []                          |
| `PublicRateLimitError`          | `PublicRateLimitErrorEnvelope`          | 429    | Too many requests. Please try again later.             | `rate_limited`          | [`retry_after_seconds: 42`] |
| `PublicInternalServerError`     | `PublicInternalServerErrorEnvelope`     | 500    | An unexpected server error occurred.                   | `internal_server_error` | []                          |
| `PublicServiceUnavailableError` | `PublicServiceUnavailableErrorEnvelope` | 503    | The service is temporarily unavailable.                | `service_unavailable`   | []                          |

**Missing from ScrambleServiceProvider:**

- `project_archived`
- `task_archived`
- `plan_limit_exceeded`
- `subscription_required`
- `task_not_trashed`
- `dashboard_service_error`
- `storage_error`
- `database_error`
- `invalid_state_transition`
- `token_rate_limited` (from RateLimitReachedException)

---

## Duplicates Identified

1. **Standard codes duplicated across:**
   - `ApiErrorFormatter::defaultCodeForStatus()`
   - `ScrambleServiceProvider::publicApiErrorResponseDefinitions()`

2. **Business codes only in runtime:**
   - `project_archived`, `task_archived`
   - `plan_limit_exceeded`
   - `subscription_required`
   - `task_not_trashed`
   - `dashboard_service_error`
   - `storage_error`, `database_error`
   - `invalid_state_transition`

3. **Infrastructure codes only in runtime:**
   - `storage_error` (S3Exception)
   - `database_error` (QueryException)

---

## Recommendations

1. **Create a PHP registry** for error codes that both `ApiErrorFormatter` and `ScrambleServiceProvider` consume
2. **Add business codes** to the registry with their metadata structures
3. **Remove duplication** from `ScrambleServiceProvider::publicApiErrorResponseDefinitions()`
4. **Generate OpenAPI schemas** from the registry instead of hardcoding
