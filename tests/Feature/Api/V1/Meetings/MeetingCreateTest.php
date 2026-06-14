<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Meetings;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

/**
 * Feature tests for meeting creation API endpoint.
 *
 * Tests the POST /api/v1/meetings endpoint which creates meetings in both the local database
 * and Zoom via API integration. These tests verify:
 * - Successful meeting creation with proper Zoom API interaction
 * - Error handling when Zoom API fails
 * - Request validation
 * - Sync status transitions (pending → active → failed)
 * - Idempotency guarantees
 * - Database integrity and rollback behavior
 *
 * Level: Feature/Integration testing
 */
class MeetingCreateTest extends TestCase
{
    use InteractsWithZoom, ProjectSetup, RefreshDatabase, SubscriptionHelpers;

    /** @test */
    public function meeting_can_be_created_successfully(): void
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

        $response = $this->withoutExceptionHandling()->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $meetingResponse = $response->json('data');

        $zoomFake->assertMeetingCreated(
            topic: $postBody['topic'],
            agenda: $postBody['agenda'],
            duration: $postBody['duration'],
        );

        // Assert database state: meeting is active after successful Zoom create
        $this->assertDatabaseHas('meetings', [
            'topic' => $meetingResponse['topic'],
            'sync_status' => 'active',
        ]);

        // Assert API response includes sync_status and synced_at is set
        $response->assertJsonPath('data.sync_status', 'active');
        $this->assertNotNull(\App\Models\Meeting::where('topic', 'test-repo')->firstOrFail()->synced_at);
    }

    /** @test */
    public function returns_error_when_zoom_creation_fails(): void
    {
        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $zoomFake = $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $zoomFake->assertNoMeetingsCreated();

        $response->assertBadRequest();
    }

    /** @test */
    public function validates_meeting_creation_request(): void
    {
        $postBody = [
            'topic' => '',
            'agenda' => '',
            'duration' => 'not-an-integer',
            'password' => '',
            'join_before_host' => 'not-a-boolean',
            'start_time' => Carbon::now()->subWeek()->toIso8601String(),
            'timezone' => 'invalid/timezone',
        ];

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'topic',
            'agenda',
            'duration',
            'password',
            'join_before_host',
            'start_time',
            'timezone',
        ]);
    }

    /** @test */
    public function start_time_must_be_iso_8601_with_timezone_offset(): void
    {
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => '2024-05-18T18:00:07',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    /** @test */
    public function it_marks_failed_when_zoom_create_throws(): void
    {
        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $this->assertDatabaseHas('meetings', [
            'topic' => $postBody['topic'],
            'sync_status' => 'failed',
            'sync_error' => 'Zoom request failed.',
        ]);
    }

    /** @test */
    public function it_increments_sync_attempts_on_failure(): void
    {
        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $this->assertDatabaseHas('meetings', [
            'topic' => $postBody['topic'],
            'sync_status' => 'failed',
            'sync_attempts' => 1,
        ]);
    }

    /** @test */
    public function normal_index_excludes_pending_and_failed_meetings(): void
    {
        $this->fakeZoom();

        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $response = $this->getJson(route('api.v1.meetings.index', ['project' => $this->project->slug]));

        $response->assertJsonCount(0, 'data');
    }

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
        $response1->assertCreated();
        $meetingId1 = $response1->json('data.id');

        // Second request with same idempotency key
        $response2 = $this->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody, $headers);
        $response2->assertCreated();
        $meetingId2 = $response2->json('data.id');

        // Should return the same meeting
        $this->assertEquals($meetingId1, $meetingId2);

        // Should only create one meeting in Zoom
        $zoomFake->assertMeetingCreated(
            topic: $postBody['topic'],
            agenda: $postBody['agenda'],
            duration: $postBody['duration'],
        );
    }

    /** @test */
    public function different_idempotency_key_creates_new_operation(): void
    {
        $this->createProSubscription($this->user);

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
        $response1->assertCreated();

        // Second request with different idempotency key
        $response2 = $this->postJson(
            route('api.v1.meetings.store', ['project' => $this->project->slug]),
            $postBody,
            ['Idempotency-Key' => 'different-key-'.Str::uuid()]
        );
        $response2->assertCreated();

        // Should create two meetings in Zoom
        $this->assertCount(2, $zoomFake->meetingsToCreate);
        $zoomFake->assertMeetingCreated(
            topic: $postBody['topic'],
            agenda: $postBody['agenda'],
            duration: $postBody['duration'],
        );
    }
}
