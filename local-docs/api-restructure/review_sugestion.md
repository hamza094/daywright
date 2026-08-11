# Resolve CodeRabbit Review Issues

This plan outlines the fixes for the six issues identified by CodeRabbit to improve security and fix logic bugs in the codebase.

## User Review Required

Please review the proposed change for the Zoom Tokens endpoint. I propose handling the ability check directly in the controller instead of the route middleware, because the requested action (`join` vs `start`) is determined by the request body payload, which route middleware cannot easily inspect dynamically.

## Open Questions

None.

## Proposed Changes

### Auth Component

Fix the privilege escalation vulnerability where tokens with empty scopes can create wildcard tokens.

#### [MODIFY] CreateApiTokenAction.php

- **File**: `app/Actions/Auth/CreateApiTokenAction.php`
- **Change**: On line 77, remove `empty($callerAbilities) || ` from the `if` statement. The new condition will just be `if (in_array('*', $callerAbilities, true)) {`.

### Project Component

Fix the bulk deletion audit log issue to correctly report only actually deleted projects.

#### [MODIFY] BulkDeleteProjectsAction.php

- **File**: `app/Actions/Project/BulkDeleteProjectsAction.php`
- **Change**: On line 36, check the return value of `forceDeleteIfAbandoned`. Update the code to:
  ```php
  if ($this->projectService->forceDeleteIfAbandoned($project)) {
      $deletedProjectIds[] = $project->id;
  }
  ```

### Task Component

Fix the issue where form-encoded task status updates (which are sent as strings like `"2"`) are silently ignored by the DTO.

#### [MODIFY] TaskUpdateData.php

- **File**: `app/DataTransferObjects/Task/TaskUpdateData.php`
- **Change**: Modify `statusId()` to cast numeric strings to integers instead of strictly expecting an integer type:
  ```php
  public function statusId(): ?int
  {
      $statusId = $this->attributes['status_id'] ?? null;
      return is_numeric($statusId) ? (int) $statusId : null;
  }
  ```

### Billing Component

Fix the missing Paddle plan ID validation in the swap method.

#### [MODIFY] SubscriptionService.php

- **File**: `app/Services/Paddle/SubscriptionService.php`
- **Change**: In the `swap` method, add `$this->validatePlanConfig($plan, 'swap');` right before the `try {` block that calls `$lockedUser->subscription(...)->swapAndInvoice(...)`.

### Exception Handling Component

Fix the issue where dashboard and backup failures are silently swallowed.

#### [MODIFY] Handler.php

- **File**: `app/Exceptions/Handler.php`
- **Change**: Remove `DashboardServiceException::class,` from the `$dontReport` array.

### Meetings Component

Fix the issue where read-only tokens are unable to join meetings due to an overly strict route middleware.

#### [MODIFY] meetings.php

- **File**: `routes/api/v1/projects/meetings.php`
- **Change**: Change the route middleware for `/meetings/{meeting}/zoom-tokens` from `['can:access,project', 'tokenAbility:projects:write']` to just `['can:access,project']`.

#### [MODIFY] MeetingZoomTokensController.php

- **File**: `app/Http/Controllers/Api/V1/Project/MeetingZoomTokensController.php`
- **Change**: Inside the `__invoke` method, add a dynamic token ability check based on the requested action before proceeding:
  ```php
  $ability = $data->action === \App\Enums\Meeting\MeetingTokenAction::Start ? 'projects:write' : 'projects:read';
  if ($currentUser->currentAccessToken() && !$currentUser->tokenCan($ability)) {
      abort(Response::HTTP_FORBIDDEN, 'Invalid token ability.');
  }
  ```

## Verification Plan

### Automated Tests

- If there are existing tests for these features, they should pass, and they may be extended if necessary. Specifically, I will check if any tests fail due to these changes and update them.

### Manual Verification

- We can verify that the audit log now only includes deleted projects, and that task status updates via string ID work properly.
