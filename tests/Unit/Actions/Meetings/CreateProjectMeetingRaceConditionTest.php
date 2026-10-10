<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Meetings;

use App\Actions\Meetings\CreateProjectMeeting;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class CreateProjectMeetingRaceConditionTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    #[Test]
    public function it_handles_already_active_with_same_meeting_id_idempotently(): void
    {
        // Simulate webhook arriving first and setting meeting to Active
        $operationId = Str::uuid()->toString();

        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::Active,
            'sync_operation_id' => $operationId,
            'meeting_id' => 123,
            'join_url' => 'https://zoom.us/j/123',
            'start_url' => 'https://zoom.us/s/123',
            'synced_at' => now(),
        ]);

        $zoomMeeting = $this->createZoomMeeting(123, $operationId);

        // Use reflection to call the private method
        $action = app(CreateProjectMeeting::class);
        $reflection = new ReflectionClass($action);
        $method = $reflection->getMethod('markMeetingAsSynced');
        $method->setAccessible(true);

        // Should not throw an exception
        $method->invoke($action, $meeting, $zoomMeeting);

        // Meeting should still be Active with same ID
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame(123, $meeting->meeting_id);
    }

    #[Test]
    public function it_transitions_create_unknown_to_active_with_same_meeting_id(): void
    {
        // Simulate webhook arriving first and setting CreateUnknown
        $operationId = Str::uuid()->toString();

        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::CreateUnknown,
            'sync_operation_id' => $operationId,
            'meeting_id' => 123,
            'join_url' => 'https://zoom.us/j/123',
            'sync_available_at' => now(),
        ]);

        $zoomMeeting = $this->createZoomMeeting(123, $operationId);

        // Use reflection to call the private method
        $action = app(CreateProjectMeeting::class);
        $reflection = new ReflectionClass($action);
        $method = $reflection->getMethod('markMeetingAsSynced');
        $method->setAccessible(true);

        $method->invoke($action, $meeting, $zoomMeeting);

        // Meeting should now be Active
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame(123, $meeting->meeting_id);
        $this->assertNotNull($meeting->synced_at);
        $this->assertNull($meeting->sync_available_at);
    }

    #[Test]
    public function it_respects_active_claim_on_create_unknown(): void
    {
        // Simulate webhook with active claim
        $operationId = Str::uuid()->toString();

        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::CreateUnknown,
            'sync_operation_id' => $operationId,
            'meeting_id' => 123,
            'join_url' => 'https://zoom.us/j/123',
            'sync_claim_token' => 'webhook-claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => now(),
        ]);

        $zoomMeeting = $this->createZoomMeeting(123, $operationId);

        // Use reflection to call the private method
        $action = app(CreateProjectMeeting::class);
        $reflection = new ReflectionClass($action);
        $method = $reflection->getMethod('markMeetingAsSynced');
        $method->setAccessible(true);

        $method->invoke($action, $meeting, $zoomMeeting);

        // Meeting should still be CreateUnknown with claim preserved
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertSame('webhook-claim-token', $meeting->sync_claim_token);
    }

    #[Test]
    public function it_handles_conflicting_meeting_id(): void
    {
        // Simulate webhook setting different meeting_id
        $operationId = Str::uuid()->toString();

        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::Creating,
            'sync_operation_id' => $operationId,
            'meeting_id' => 999, // Different ID from webhook
            'join_url' => 'https://zoom.us/j/999',
        ]);

        $zoomMeeting = $this->createZoomMeeting(123, $operationId); // API returns 123

        // Use reflection to call the private method
        $action = app(CreateProjectMeeting::class);
        $reflection = new ReflectionClass($action);
        $method = $reflection->getMethod('markMeetingAsSynced');
        $method->setAccessible(true);

        $method->invoke($action, $meeting, $zoomMeeting);

        // Meeting should be in CreateUnknown with conflict error
        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertSame('remote_meeting_id_conflict', $meeting->sync_error);
        $this->assertSame(999, $meeting->meeting_id); // Should keep the webhook's ID
    }

    #[Test]
    public function it_ignores_a_late_uncertain_result_after_webhook_claims_the_meeting(): void
    {
        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::CreateUnknown,
            'sync_claim_token' => 'webhook-claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $action = app(CreateProjectMeeting::class);
        $method = (new ReflectionClass($action))->getMethod('markMeetingAsCreateUnknown');
        $method->setAccessible(true);
        $method->invoke($action, $meeting, new RuntimeException('late timeout'));

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertSame('webhook-claim-token', $meeting->sync_claim_token);
    }

    #[Test]
    public function it_ignores_a_late_failure_after_webhook_activates_the_meeting(): void
    {
        $meeting = Meeting::factory()->for($this->project)->create([
            'user_id' => $this->user->id,
            'sync_status' => MeetingSyncStatus::Active,
            'meeting_id' => 123,
        ]);

        $action = app(CreateProjectMeeting::class);
        $method = (new ReflectionClass($action))->getMethod('markMeetingAsFailed');
        $method->setAccessible(true);
        $method->invoke($action, $meeting, new RuntimeException('late rejection'));

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame(123, $meeting->meeting_id);
    }

    private function createZoomMeeting(int $meetingId, string $operationId): ZoomMeeting
    {
        return new ZoomMeeting(
            meeting_id: $meetingId,
            topic: 'test-repo',
            agenda: 'test-description',
            created_at: '2026-09-18 10:00:00',
            duration: 30,
            start_time: '2026-09-19 10:00:00',
            start_url: "https://zoom.us/s/{$meetingId}",
            join_url: "https://zoom.us/j/{$meetingId}",
            status: 'waiting',
            timezone: 'UTC',
            password: 'metingpass',
            join_before_host: false,
            tracking_fields: ['Daywright Operation ID' => $operationId],
        );
    }
}
