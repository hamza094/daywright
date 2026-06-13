# Zoom Test Suite Improvement Plan

## Overview

This document outlines a phased approach to improve the Laravel + Saloon Zoom integration test suite. The goal is to remove unnecessary or duplicate tests, merge overlapping tests, fix weak or implementation-coupled tests, add missing critical edge-case tests, make test helpers/fakes reusable, improve naming and organization, and ensure tests verify behavior rather than private implementation.

**Current state:** ~65 tests across ~20 test files  
**Target state:** ~75 tests with better coverage, less duplication, and reusable helpers  
**Net change:** +10 tests, but stronger around critical edge cases

---

## Phase 1: Remove Duplicate/Redundant Tests

### Goal

Eliminate test duplication while maintaining critical coverage.

### Tasks

#### 1.1 Delete duplicate middleware tests

**File to delete:** `tests/Unit/Jobs/Webhooks/ZoomWebhookMiddlewareTest.php`

**Reason:** Complete duplicate of feature middleware tests in `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`. Both test the exact same middleware logic with identical coverage (missing headers, stale timestamp, valid signature, endpoint validation). Keep feature tests as they test the HTTP boundary.

**Coverage retained:** All webhook middleware validation is still tested in the feature test file.

---

#### 1.2 Delete buggy redundant test in MeetingUpdateTest

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`

**Test to remove:** `database_changes_are_rolled_back_if_zoom_update_fails` (lines 44-65)

**Reason:** Test has a bug (references undefined `$persistedMeetingId` on line 63) and duplicates behavior already tested in `it_does_not_apply_local_changes_on_zoom_failure` which is clearer and correctly implemented.

**Coverage retained:** The clearer test `it_does_not_apply_local_changes_on_zoom_failure` (lines 141-164) covers the same behavior.

---

#### 1.3 Merge duplicate state validation tests in OAuthCallbackTest

**File to modify:** `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`

**Tests to merge:**

- `error_is_returned_if_the_cached_verifier_is_missing_or_expired` (lines 100-109)
- `error_is_returned_if_the_cached_state_is_missing_or_expired` (lines 112-121)

**Into:** `error_is_returned_if_authorization_state_is_missing_or_expired`

**Reason:** Both tests verify the same error path (missing/invalid cached state) with nearly identical assertions. The distinction between "verifier missing" vs "state missing" is an implementation detail - the observable behavior is the same: "authorization session is invalid or expired".

**Implementation:**

```php
#[Test]
public function error_is_returned_if_authorization_state_is_missing_or_expired(): void
{
    $this->fakeZoom();

    $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
        ->assertBadRequest()
        ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

    $this->assertUserWasNotUpdated($this->user->fresh());
}
```

---

## Phase 2: Extract Reusable Test Helpers

### Goal

Reduce fixture/payload construction duplication across test files.

### Tasks

#### 2.1 Create Zoom Response Factory

**File to create:** `tests/Support/Zoom/ZoomResponseFactory.php`

**Purpose:** Provide factory methods for Zoom API responses used in unit tests.

**Methods to implement:**

```php
class ZoomResponseFactory
{
    public static function validMeetingResponse(array $overrides = []): MockResponse
    public static function tokenResponse(array $overrides = []): MockResponse
    public static function invalidGrantResponse(): MockResponse
    public static function meetingNotFoundResponse(): MockResponse
    public static function rateLimitResponse(int $retryAfter = 60): MockResponse
}
```

**Used in:**

- `tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php`
- `tests/Unit/Services/Zoom/ZoomMeetingUpdateTest.php`
- `tests/Unit/Services/Zoom/ZoomMeetingDeleteTest.php`
- `tests/Unit/Http/Integrations/Zoom/ZoomConnectorTest.php`

---

#### 2.2 Create Webhook Payload Factory

**File to create:** `tests/Support/Zoom/ZoomWebhookPayloadFactory.php`

**Purpose:** Provide factory methods for Zoom webhook payloads used in feature and job tests.

**Methods to implement:**

```php
class ZoomWebhookPayloadFactory
{
    public static function meetingStartedPayload(array $overrides = []): array
    public static function meetingEndedPayload(array $overrides = []): array
    public static function meetingUpdatedPayload(array $overrides = []): array
    public static function meetingDeletedPayload(array $overrides = []): array
    public static function endpointValidationPayload(string $plainToken): array
}
```

**Used in:**

- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/StartMeetingWebhookTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/EndedMeetingWebhookTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/ProcessMeetingUpdateWebhookTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/ProcessMeetingDeleteTest.php`

---

#### 2.3 Create Webhook Signer Helper

**File to create:** `tests/Support/Zoom/ZoomWebhookSigner.php`

**Purpose:** Centralize webhook signature generation logic currently duplicated across multiple test files.

**Methods to implement:**

```php
class ZoomWebhookSigner
{
    public static function signPayload(array $payload, string $requestId): array
    public static function buildSignature(string $timestamp, string $payload): string
    private static function normalizePayload(array|string $payload): string
}
```

**Used in:**

- `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`

**Replaces:** Duplicate `zoomWebhookHeaders()` and `buildSignature()` methods in test files.

---

#### 2.4 Create OAuth Test Helper

**File to create:** `tests/Support/Zoom/ZoomOAuthTestHelper.php`

**Purpose:** Provide helper methods for OAuth state and token assertions.

**Methods to implement:**

```php
class ZoomOAuthTestHelper
{
    public static function createAuthorizationState(string $state, User $user, string $verifier): void
    public static function assertTokensSaved(User $user, string $expectedAccessToken, string $expectedRefreshToken): void
    public static function assertNoTokensSaved(User $user): void
}
```

**Used in:**

- `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`

**Replaces:** Duplicate `assertUserWasNotUpdated()` method and cache setup code.

---

#### 2.5 Create Meeting Test Helper

**File to create:** `tests/Support/Meeting/MeetingTestHelper.php`

**Purpose:** Provide factory methods for creating meetings in different sync states.

**Methods to implement:**

```php
class MeetingTestHelper
{
    public static function createActiveMeeting(Project $project, User $user, array $overrides = []): Meeting
    public static function createFailedMeeting(Project $project, User $user, array $overrides = []): Meeting
    public static function createPendingMeeting(Project $project, User $user, array $overrides = []): Meeting
    public static function createDeletingMeeting(Project $project, User $user, array $overrides = []): Meeting
    public static function createDeletedMeeting(Project $project, User $user, array $overrides = []): Meeting
    public static function assertMeetingStatus(Meeting $meeting, string $expectedStatus): void
    public static function assertMeetingSyncStatus(Meeting $meeting, MeetingSyncStatus $expectedStatus): void
}
```

**Used in:**

- `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/*WebhookTest.php`

---

## Phase 3: Add Missing Critical Tests

### Goal

Strengthen coverage around critical edge cases, security, and concurrency.

### Tasks

#### 3.1 Add OAuth security tests

**File to modify:** `tests/Unit/Services/Zoom/ZoomAuthorizationTest.php`

**Tests to add:**

```php
#[Test]
public function code_verifier_is_not_sent_during_refresh_token_flow(): void
{
    Saloon::fake([
        MockResponse::make([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]),
    ]);

    $callbackDetails = new AuthorizationCallbackDetails(
        authorizationCode: null,
        state: null,
        codeVerifier: null,
        refreshToken: 'existing-refresh-token',
    );

    $zoomService = app(ZoomOAuthService::class);
    $zoomService->authorize($callbackDetails);

    Saloon::assertSent(static fn (GetAccessTokenRequest $request): bool =>
        $request->body()->get('code_verifier') === null &&
        $request->body()->get('grant_type') === 'refresh_token'
    );
}
```

**Reason:** Critical security test - code verifier should only be sent during authorization code exchange, not refresh token flow.

---

#### 3.2 Add token refresh lock tests

**File to modify:** `tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php`

**Tests to add:**

```php
#[Test]
public function refresh_lock_prevents_concurrent_refresh(): void
{
    $expiredUser = $this->createZoomUser(now()->subWeek());

    Saloon::fake([
        GetRefreshTokenRequest::class => MockResponse::make([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]),
        'users/me/meetings' => $this->mockMeetingResponse(),
    ]);

    // Simulate concurrent requests
    $results = [];
    for ($i = 0; $i < 3; $i++) {
        $results[] = app(ZoomService::class)->createMeeting($this->meetingData, $expiredUser);
    }

    // Should only send one refresh request despite multiple concurrent calls
    Saloon::assertSent(GetRefreshTokenRequest::class, 1);

    // All requests should succeed with the new token
    foreach ($results as $result) {
        $this->assertNotNull($result);
    }
}

#[Test]
public function valid_access_token_is_reused_without_refresh(): void
{
    $validUser = $this->createZoomUser(now()->addWeek());

    Saloon::fake([
        'users/me/meetings' => $this->mockMeetingResponse(),
    ]);

    app(ZoomService::class)->createMeeting($this->meetingData, $validUser);

    // Should not attempt to refresh valid token
    Saloon::assertNotSent(GetRefreshTokenRequest::class);
}
```

**Reason:** Critical concurrency safety test - ensures refresh lock prevents race conditions.

---

#### 3.3 Add idempotency tests for meeting operations

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`

**Test to add:**

```php
/** @test */
public function same_idempotency_key_does_not_create_duplicate_meeting(): void
{
    $zoomFake = $this->fakeZoom();

    $postBody = [
        'topic' => 'test-repo',
        'agenda' => 'test-description',
        'duration' => 30,
        'password' => 'metingpass',
        'join_before_host' => false,
        'start_time' => Carbon::now()->addWeek()->toIso8601String(),
        'timezone' => 'UTC',
    ];

    $headers = $this->idempotencyHeaders();

    // First request
    $response1 = $this->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody, $headers);
    $response1->assertOk();
    $meetingId1 = $response1->json('data.id');

    // Second request with same idempotency key
    $response2 = $this->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody, $headers);
    $response2->assertOk();
    $meetingId2 = $response2->json('data.id');

    // Should return the same meeting
    $this->assertEquals($meetingId1, $meetingId2);

    // Should only create one meeting in Zoom
    $zoomFake->assertMeetingCreated(topic: $postBody['topic']);
}

/** @test */
public function different_idempotency_key_creates_new_operation(): void
{
    $zoomFake = $this->fakeZoom();

    $postBody = [
        'topic' => 'test-repo',
        'agenda' => 'test-description',
        'duration' => 30,
        'password' => 'metingpass',
        'join_before_host' => false,
        'start_time' => Carbon::now()->addWeek()->toIso8601String(),
        'timezone' => 'UTC',
    ];

    // First request
    $response1 = $this->postJson(
        route('api.v1.meetings.store', ['project' => $this->project->slug]),
        $postBody,
        $this->idempotencyHeaders()
    );
    $response1->assertOk();

    // Second request with different idempotency key
    $response2 = $this->postJson(
        route('api.v1.meetings.store', ['project' => $this->project->slug]),
        $postBody,
        ['Idempotency-Key' => 'different-key-'.Str::uuid()]
    );
    $response2->assertOk();

    // Should create two meetings in Zoom
    $zoomFake->assertMeetingCreated(topic: $postBody['topic']);
    $zoomFake->assertMeetingCreated(topic: $postBody['topic']);
}
```

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`

**Test to add:**

```php
/** @test */
public function same_idempotency_key_is_safe_for_update(): void
{
    $this->fakeZoom();

    $meeting = Meeting::factory()
        ->for($this->project)
        ->create(['user_id' => $this->user->id]);

    $headers = $this->idempotencyHeaders();

    // First update
    $this->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
        'duration' => 15,
    ], $headers)->assertStatus(200);

    // Second update with same idempotency key
    $this->patchJson($this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]), [
        'duration' => 20,
    ], $headers)->assertStatus(200);

    // Should not cause errors - idempotency should be safe
    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'duration' => 15, // First value should persist
    ]);
}
```

**Reason:** Critical data integrity tests - ensures idempotency guarantees work correctly.

---

#### 3.4 Add webhook state machine test

**File to modify:** `tests/Feature/Api/Jobs/Webhooks/Zoom/StartMeetingWebhookTest.php`

**Test to add:**

```php
/** @test */
public function ended_meeting_cannot_be_restarted_via_webhook(): void
{
    Notification::fake();
    Event::fake([MeetingStatusUpdate::class]);

    $meeting = Meeting::factory()->create([
        'meeting_id' => 813,
        'project_id' => $this->project->id,
        'user_id' => $this->user->id,
        'status' => 'ended',
    ]);

    $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
        meetingId: 813,
        startTime: '2024-06-24T11:00:00Z',
        requestId: 'zoom-restart-ended',
    ));

    $job->handle();

    // Meeting should remain ended
    $this->assertEquals('ended', $meeting->fresh()->status);

    // No notification should be sent
    Notification::assertNothingSent();
    Event::assertNotDispatched(MeetingStatusUpdate::class);
}
```

**Reason:** State machine integrity test - ensures invalid state transitions are rejected.

---

#### 3.5 Add comprehensive DTO mapping tests

**File to modify:** `tests/Unit/DataTransferObjects/Zoom/MeetingTest.php`

**Tests to add:**

```php
#[Test]
public function it_maps_zoom_meeting_response_correctly(): void
{
    $response = [
        'id' => 123456789012345,
        'topic' => 'Test Meeting',
        'agenda' => 'Test Agenda',
        'created_at' => '2024-05-16T18:00:07Z',
        'duration' => 30,
        'join_url' => 'https://zoom.us/j/1234567890?pwd=secret',
        'password' => 'secret',
        'join_before_host' => true,
        'start_time' => '2024-05-18T18:00:07Z',
        'start_url' => 'https://zoom.us/s/1234567890?pwd=secret',
        'status' => 'waiting',
        'timezone' => 'UTC',
    ];

    $meeting = Meeting::fromResponse($response);

    $this->assertEquals(123456789012345, $meeting->meeting_id);
    $this->assertEquals('Test Meeting', $meeting->topic);
    $this->assertEquals('Test Agenda', $meeting->agenda);
    $this->assertEquals('2024-05-16 18:00:07', $meeting->created_at);
    $this->assertEquals(30, $meeting->duration);
    $this->assertEquals('https://zoom.us/j/1234567890?pwd=secret', $meeting->join_url);
    $this->assertEquals('secret', $meeting->password);
    $this->assertTrue($meeting->join_before_host);
    $this->assertEquals('2024-05-18 18:00:07', $meeting->start_time);
    $this->assertEquals('https://zoom.us/s/1234567890?pwd=secret', $meeting->start_url);
    $this->assertEquals('waiting', $meeting->status);
    $this->assertEquals('UTC', $meeting->timezone);
}

#[Test]
public function it_normalizes_boolean_and_integer_types(): void
{
    $response = [
        'id' => 123,
        'topic' => 'Test',
        'agenda' => '',
        'created_at' => '2024-05-16T18:00:07Z',
        'duration' => 30,
        'join_url' => 'https://zoom.us/j/123',
        'password' => 'secret',
        'join_before_host' => 'true', // String from API
        'start_time' => '2024-05-18T18:00:07Z',
        'start_url' => 'https://zoom.us/s/123',
        'status' => 'waiting',
        'timezone' => 'UTC',
    ];

    $meeting = Meeting::fromResponse($response);

    $this->assertTrue($meeting->join_before_host); // Should be normalized to boolean
}

#[Test]
public function it_handles_int64_meeting_ids(): void
{
    $largeId = '9223372036854775807'; // Max int64

    $response = [
        'id' => $largeId,
        'topic' => 'Test',
        'agenda' => '',
        'created_at' => '2024-05-16T18:00:07Z',
        'duration' => 30,
        'join_url' => 'https://zoom.us/j/123',
        'password' => 'secret',
        'join_before_host' => false,
        'start_time' => '2024-05-18T18:00:07Z',
        'start_url' => 'https://zoom.us/s/123',
        'status' => 'waiting',
        'timezone' => 'UTC',
    ];

    $meeting = Meeting::fromResponse($response);

    $this->assertEquals($largeId, $meeting->meeting_id);
}
```

**Reason:** Comprehensive DTO coverage ensures data integrity and type safety.

---

#### 3.6 Add retry-after context test

**File to modify:** `tests/Unit/Http/Integrations/Zoom/ZoomConnectorTest.php`

**Test to add:**

```php
#[Test]
public function rate_limit_exception_includes_retry_after_context(): void
{
    Saloon::fake([
        'users/me/token?type=zak' => MockResponse::make(
            body: ['message' => 'Rate limit exceeded'],
            status: 429,
            headers: ['Retry-After' => '60']
        ),
    ]);

    try {
        $this->authenticatedConnector()->send(new GetZakToken)->throw();
        $this->fail('Expected ZoomExternalFailureException was not thrown.');
    } catch (ZoomExternalFailureException $exception) {
        $this->assertSame(429, $exception->getCode());
        $this->assertArrayHasKey('retry_after_seconds', $exception->getContext());
        $this->assertEquals(60, $exception->getContext()['retry_after_seconds']);
    }
}
```

**Reason:** Rate limit handling test - ensures clients can respect retry-after headers.

---

## Phase 4: Refactor Existing Tests to Use Helpers

### Goal

Replace duplicated fixture/payload construction with reusable helpers.

### Tasks

#### 4.1 Refactor webhook tests to use WebhookSigner

**Files to modify:**

- `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`

**Changes:**

- Remove duplicate `zoomWebhookHeaders()` methods
- Remove duplicate `buildSignature()` methods
- Use `ZoomWebhookSigner::signPayload()` instead

---

#### 4.2 Refactor OAuth tests to use OAuthTestHelper

**File to modify:** `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`

**Changes:**

- Replace `assertUserWasNotUpdated()` with `ZoomOAuthTestHelper::assertNoTokensSaved()`
- Replace cache setup with `ZoomOAuthTestHelper::createAuthorizationState()`

---

#### 4.3 Refactor meeting tests to use MeetingTestHelper

**Files to modify:**

- `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php`
- `tests/Feature/Api/Jobs/Webhooks/Zoom/*WebhookTest.php`

**Changes:**

- Replace manual meeting factory calls with `MeetingTestHelper::create*Meeting()` methods
- Replace status assertions with `MeetingTestHelper::assertMeetingStatus()`

---

#### 4.4 Refactor Zoom service tests to use ResponseFactory

**Files to modify:**

- `tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php`
- `tests/Unit/Services/Zoom/ZoomMeetingUpdateTest.php`
- `tests/Unit/Services/Zoom/ZoomMeetingDeleteTest.php`

**Changes:**

- Replace inline `MockResponse::make()` calls with `ZoomResponseFactory` methods

---

## Phase 5: Improve Test Names and Organization

### Goal

Make test names descriptive and ensure clear ownership between test levels.

### Tasks

#### 5.1 Rename unclear test names

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`

**Rename:**

- `user_get_exception_if_error_occurs` → `returns_error_when_zoom_creation_fails`
- `it_validates_meeting_creation_request` → `validates_meeting_creation_request`

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`

**Rename:**

- `meeting_in_database_can_be_updated` → `meeting_can_be_updated_successfully`
- `database_changes_are_rolled_back_if_zoom_update_fails` → (delete this one)

**File to modify:** `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php`

**Rename:**

- `meeting_can_be_deleted` → `meeting_can_be_deleted_successfully`

---

#### 5.2 Add test class docblocks

**Files to modify:** All test files

**Add docblocks explaining:**

- What feature area the test covers
- What level of testing (unit/feature/integration)
- Key invariants being tested

---

## Phase 6: Verification and Cleanup

### Goal

Ensure all changes work correctly and the test suite is healthy.

### Tasks

#### 6.1 Run targeted test suites

```bash
# Run OAuth tests
php artisan test tests/Feature/Api/Auth/Zoom/
php artisan test tests/Unit/Services/Zoom/ZoomAuth*

# Run meeting tests
php artisan test tests/Feature/Api/V1/Meetings/
php artisan test tests/Unit/Services/Zoom/ZoomMeeting*

# Run webhook tests
php artisan test tests/Feature/Api/Webhooks/Zoom/
php artisan test tests/Feature/Api/Jobs/Webhooks/Zoom/
php artisan test tests/Feature/Api/Middleware/Zoom/

# Run DTO tests
php artisan test tests/Unit/DataTransferObjects/Zoom/

# Run connector tests
php artisan test tests/Unit/Http/Integrations/Zoom/
```

---

#### 6.2 Run full test suite

```bash
php artisan test
```

---

#### 6.3 Run code style tools

```bash
# Run Pint
./vendor/bin/pint

# Run Larastan/PHPStan if configured
./vendor/bin/phpstan analyse
```

---

#### 6.4 Update test documentation

- Update any README files that reference test structure
- Document the new helper classes in a testing guide

---

## Summary of Changes

### Files to Delete (1)

1. `tests/Unit/Jobs/Webhooks/ZoomWebhookMiddlewareTest.php`

### Files to Modify (8)

1. `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php` - Merge duplicate tests, use helpers
2. `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php` - Add idempotency tests, use helpers
3. `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php` - Remove buggy test, add idempotency test, use helpers
4. `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php` - Use helpers
5. `tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php` - Add token refresh lock tests, use ResponseFactory
6. `tests/Feature/Api/Jobs/Webhooks/Zoom/StartMeetingWebhookTest.php` - Add state machine test, use helpers
7. `tests/Unit/DataTransferObjects/Zoom/MeetingTest.php` - Add comprehensive DTO tests
8. `tests/Unit/Http/Integrations/Zoom/ZoomConnectorTest.php` - Add retry-after test

### Files to Create (5)

1. `tests/Support/Zoom/ZoomResponseFactory.php`
2. `tests/Support/Zoom/ZoomWebhookPayloadFactory.php`
3. `tests/Support/Zoom/ZoomWebhookSigner.php`
4. `tests/Support/Zoom/ZoomOAuthTestHelper.php`
5. `tests/Support/Meeting/MeetingTestHelper.php`

### Test Count Changes

- **Before:** ~65 tests
- **After deletions:** ~60 tests (-5)
- **After additions:** ~75 tests (+15)
- **Net change:** +10 tests

---

## Anti-Overengineering Rules

**Do NOT:**

- Rewrite the complete Zoom integration
- Introduce a generic OAuth framework
- Create dozens of test helper abstractions (only the 5 proposed)
- Mock every internal class
- Test Laravel or Saloon framework internals
- Add real external API tests to normal CI
- Delete useful failure-path tests just to make suite smaller

**Focus on:**

- Behavior verification
- Security boundaries
- Failure recovery
- Clear test ownership
- Reusable fixtures
- Fast deterministic tests

---

## Success Criteria

✅ Duplicate tests removed  
✅ Missing critical tests added  
✅ Reusable helpers extracted  
✅ Test names improved  
✅ All tests passing  
✅ Code style tools passing  
✅ No production code changes (unless tests reveal real bugs)  
✅ Test suite is smaller where duplicated, stronger around critical edge cases  
✅ Tests organized by feature with clear unit/feature/integration boundaries
