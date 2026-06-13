# Zoom Test Features Implementation Plan

## Overview

This plan outlines the codebase changes needed to support the critical edge case tests that were removed during Phase 3 of the test suite improvement. These tests are valuable for security, concurrency, and data integrity but require specific codebase updates to pass.

## Features to Implement

### 1. Idempotency Test Support

**Tests affected:**

- `same_idempotency_key_does_not_create_duplicate_meeting` (MeetingCreateTest.php)
- `different_idempotency_key_creates_new_operation` (MeetingCreateTest.php)
- `same_idempotency_key_is_safe_for_update` (MeetingUpdateTest.php)

**Codebase changes needed:**

- Update `fakeZoom()` helper in `InteractsWithZoom` trait to properly track and assert on multiple meeting creation/update requests
- Ensure the ZoomServiceFake can handle multiple requests with different idempotency keys
- Verify idempotency middleware is properly configured and working

**Implementation steps:**

1. Review current `fakeZoom()` implementation in `Tests\Traits\InteractsWithZoom`
2. Add tracking for multiple requests in ZoomServiceFake
3. Add assertion methods to verify number of requests made
4. Test with the idempotency tests to ensure they pass

**Priority:** High - Critical for preventing duplicate operations

---

### 2. OAuth Refresh Token Flow Support

**Test affected:**

- `code_verifier_is_not_sent_during_refresh_token_flow` (ZoomAuthorizationTest.php)

**Codebase changes needed:**

- Add `refresh_token` parameter to `AuthorizationCallbackDetails` DTO
- Update `ZoomOAuthService::authorize()` to handle refresh token flow
- Ensure code_verifier is not included in refresh token requests

**Implementation steps:**

1. Update `App\DataTransferObjects\Zoom\AuthorizationCallbackDetails`:

   ```php
   public function __construct(
       public ?string $authorizationCode = null,
       public ?string $state = null,
       public ?string $codeVerifier = null,
       public ?string $refreshToken = null,
   ) {}
   ```

2. Update `App\Services\Zoom\ZoomOAuthService::authorize()` to:
   - Check if `refreshToken` is provided
   - If yes, use refresh token flow without code_verifier
   - If no, use authorization code flow with code_verifier

3. Add test to verify code_verifier is not sent in refresh token flow

**Priority:** High - Important security boundary

---

### 3. Token Refresh Lock Implementation

**Test affected:**

- `refresh_lock_prevents_concurrent_refresh` (ZoomMeetingCreateTest.php)

**Codebase changes needed:**

- Implement a locking mechanism to prevent concurrent token refreshes
- Use Laravel's cache locking or Redis locks
- Ensure only one refresh request is made even with concurrent calls

**Implementation steps:**

1. Add lock mechanism in `ZoomOAuthService` or `ZoomService`:

   ```php
   $lock = Cache::lock("zoom:refresh:{$userId}", 10);

   if ($lock->get()) {
       try {
           // Perform token refresh
       } finally {
           $lock->release();
       }
   }
   ```

2. Adjust test to use proper locking mechanism
3. Ensure lock timeout is reasonable (e.g., 10 seconds)

**Priority:** High - Critical for concurrency safety

---

### 4. DTO Type Normalization

**Test affected:**

- `it_normalizes_boolean_and_integer_types` (MeetingTest.php)

**Codebase changes needed:**

- Add type normalization logic to `App\DataTransferObjects\Zoom\Meeting`
- Handle API responses that send booleans as strings ('true', 'false')
- Handle integer values that might come as strings

**Implementation steps:**

1. Update `App\DataTransferObjects\Zoom\Meeting` to normalize types:

   ```php
   private static function normalizeBool(mixed $value): bool
   {
       if (is_bool($value)) {
           return $value;
       }
       if (is_string($value)) {
           return in_array(strtolower($value), ['true', '1', 'yes']);
       }
       return (bool) $value;
   }
   ```

2. Apply normalization to `join_before_host` and other boolean fields
3. Add test to verify normalization works correctly

**Priority:** Medium - Important for handling API type variations

---

### 5. Rate Limit Exception Context

**Test affected:**

- `rate_limit_exception_includes_retry_after_context` (ZoomConnectorTest.php)

**Codebase changes needed:**

- Add `getContext()` method to `ZoomExternalFailureException`
- Include retry-after header value in exception context
- Update connector to pass retry-after information to exception

**Implementation steps:**

1. Update `App\Exceptions\Integrations\Zoom\ZoomExternalFailureException`:

   ```php
   private array $context = [];

   public function __construct(string $message, int $code = 0, array $context = [])
   {
       parent::__construct($message, $code);
       $this->context = $context;
   }

   public function getContext(): array
   {
       return $this->context;
   }
   ```

2. Update `ZoomConnector` to extract retry-after header and pass to exception:

   ```php
   $retryAfter = $response->header('Retry-After');
   throw new ZoomExternalFailureException(
       $message,
       $response->status(),
       ['retry_after_seconds' => $retryAfter]
   );
   ```

3. Add test to verify context is included in exception

**Priority:** Medium - Important for proper rate limit handling

---

## Implementation Order

1. **Phase 1:** OAuth Refresh Token Flow (High security priority)
2. **Phase 2:** DTO Type Normalization (Medium complexity)
3. **Phase 3:** Rate Limit Exception Context (Medium complexity)
4. **Phase 4:** Token Refresh Lock (High complexity, requires testing)
5. **Phase 5:** Idempotency Test Support (Depends on understanding current fake implementation)

## Testing Strategy

For each feature:

1. Implement the codebase changes
2. Add the corresponding test back to the test suite
3. Run the specific test to verify it passes
4. Run related tests to ensure no regressions
5. Update documentation if needed

## Notes

- These features should be implemented incrementally
- Each feature should be tested independently
- Consider backward compatibility when updating DTOs
- Lock implementation should use existing Laravel cache mechanisms
- Rate limit handling should be consistent across all Zoom API calls
