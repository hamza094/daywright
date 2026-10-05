<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\Meetings\PerformZoomMeetingRecovery;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Jobs\RecoverZoomMeetingOperationJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

final class RecoverZoomMeetingOperationJobTest extends TestCase
{
    use InteractsWithZoom, RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->zoom = $this->fakeZoom()->meetingNotFound();
    }

    /** @test */
    public function it_recovers_update_when_zoom_already_has_requested_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Old Topic',
            'duration' => 30,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic', 'duration' => 45]),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'New Topic', 45));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals('New Topic', $meeting->topic);
        $this->assertEquals(45, $meeting->duration);
        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
    }

    /** @test */
    public function it_retries_update_when_zoom_still_has_old_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Old Topic',
            'duration' => 30,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic', 'duration' => 45]),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'Old Topic', 30));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals('New Topic', $meeting->topic);
        $this->assertEquals(45, $meeting->duration);
        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
    }

    /** @test */
    public function it_finalizes_delete_when_zoom_meeting_is_already_deleted(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_type' => MeetingSyncOperationType::Delete,
            'sync_operation_id' => 'op-123',
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_claim_token);
    }

    /** @test */
    public function it_schedules_retry_when_zoom_not_found(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertNotNull($meeting->sync_available_at);
    }

    /** @test */
    public function it_skips_when_claim_token_expired(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->subMinute(),
        ]);

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
    }

    /** @test */
    public function it_skips_when_operation_id_changed(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'new-op-id',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'old-op-id', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
    }

    /** @test */
    public function it_handles_empty_payload_as_manual_review(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode([]),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'New Topic', 45));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::UpdateFailed, $meeting->sync_status);
        $this->assertEquals('invalid_payload', $meeting->sync_error);
    }

    /** @test */
    public function it_handles_null_payload_as_manual_review(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => null,
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'New Topic', 45));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::UpdateFailed, $meeting->sync_status);
        $this->assertEquals('invalid_payload', $meeting->sync_error);
    }

    /** @test */
    public function it_handles_null_lease_safely(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => null,
        ]);

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
    }

    /** @test */
    public function it_uses_zoom_retry_after_delay_for_rate_limits(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 0,
        ]);

        $this->zoom = $this->fakeZoom()->shouldFailWithException(
            new \App\Exceptions\Integrations\Zoom\ZoomRateLimitException(120)
        );

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertEquals(1, $meeting->sync_attempts);
        $this->assertEquals(now()->addSeconds(120)->toDateTimeString(), $meeting->sync_available_at->toDateTimeString());
    }

    /** @test */
    public function it_uses_fixed_backoff_when_rate_limit_has_no_retry_after(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 0,
        ]);

        $this->zoom = $this->fakeZoom()->shouldFailWithException(
            new \App\Exceptions\Integrations\Zoom\ZoomRateLimitException(null)
        );

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertEquals(1, $meeting->sync_attempts);
        $this->assertEquals(now()->addSeconds(60)->toDateTimeString(), $meeting->sync_available_at->toDateTimeString());
    }

    private function zoomMeeting(int $id, string $topic, int $duration): \App\DataTransferObjects\Zoom\Meeting
    {
        return new \App\DataTransferObjects\Zoom\Meeting(
            meeting_id: $id,
            topic: $topic,
            agenda: '',
            created_at: '2026-09-19 10:00:00',
            duration: $duration,
            start_time: '2026-09-19 10:00:00',
            start_url: "https://zoom.us/s/{$id}",
            join_url: "https://zoom.us/j/{$id}",
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
            tracking_fields: [],
        );
    }
}
