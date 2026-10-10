<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\Meetings\PerformZoomMeetingRecovery;
use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Jobs\RecoverZoomMeetingOperationJob;
use App\Models\Meeting;
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
    public function delayed_webhook_cannot_overwrite_an_update_completed_by_recovery(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 124,
            'topic' => 'Old Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-124',
            'sync_payload' => json_encode(['topic' => 'Recovered Update B']),
            'sync_claim_token' => 'claim-token',
            'sync_started_at' => now()->subSeconds(20),
            'sync_lease_expires_at' => now()->addMinutes(5),
            'last_zoom_event_timestamp' => null,
        ]);
        $oldWebhookTimestamp = (int) now()->subSeconds(10)->valueOf();
        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(124, 'Old Topic', 30));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-124', 'claim-token'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        app(HandleMeetingUpdatedWebhook::class)->handle(
            new MeetingUpdatedWebhookData(124, ['topic' => 'Delayed Update A'], 'delayed-update-a'),
            $oldWebhookTimestamp,
        );

        $this->assertSame('Recovered Update B', $meeting->refresh()->topic);
    }

    /** @test */
    public function delete_after_recovery_read_is_not_rejected_by_recovery_completion_time(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 125,
            'topic' => 'Old Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-125',
            'sync_payload' => json_encode(['topic' => 'Recovered Update']),
            'sync_claim_token' => 'claim-token',
            'sync_started_at' => now()->subMinute(),
            'sync_lease_expires_at' => now()->addMinutes(5),
            'last_zoom_event_timestamp' => null,
        ]);
        $deleteTimestamp = (int) now()->subSeconds(10)->valueOf();
        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(125, 'Recovered Update', 30));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-125', 'claim-token'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(125),
            $deleteTimestamp,
        );

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
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
    public function it_finalizes_update_when_zoom_meeting_is_missing(): void
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

        $this->assertEquals(MeetingSyncStatus::Deleted, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_operation_type);
        $this->assertNull($meeting->sync_payload);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNull($meeting->sync_available_at);
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

    /** @test */
    public function it_uses_the_retry_after_delay_from_a_rate_limit_exception(): void
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
            new \App\Exceptions\Integrations\Zoom\ZoomRateLimitException(90)
        );

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-123', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertEquals(1, $meeting->sync_attempts);
        $this->assertEquals(now()->addSeconds(90)->toDateTimeString(), $meeting->sync_available_at->toDateTimeString());
    }

    /** @test */
    public function stale_recovery_claim_cannot_send_an_update_after_the_meeting_is_reclaimed(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Old Topic',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'old-operation',
            'sync_payload' => json_encode(['topic' => 'Stale Topic']),
            'sync_claim_token' => 'old-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'Old Topic', 30));
        $zoom->beforeFindingMeeting(function () use ($meeting): void {
            Meeting::query()->whereKey($meeting->getKey())->update([
                'sync_operation_id' => 'new-operation',
                'sync_claim_token' => 'new-claim',
                'sync_lease_expires_at' => now()->addMinutes(5),
            ]);
        });

        app(PerformZoomMeetingRecovery::class)->execute($meeting, 'old-operation', 'old-claim', $zoom);

        $this->assertTrue($zoom->meetingsToUpdate->isEmpty());
        $this->assertSame('new-operation', $meeting->fresh()->sync_operation_id);
    }

    /** @test */
    public function stale_recovery_claim_cannot_send_a_delete_after_the_meeting_is_reclaimed(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Deleting,
            'sync_operation_type' => MeetingSyncOperationType::Delete,
            'sync_operation_id' => 'old-operation',
            'sync_claim_token' => 'old-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $zoom = $this->fakeZoom();

        Meeting::query()->whereKey($meeting->getKey())->update([
            'sync_operation_id' => 'new-operation',
            'sync_claim_token' => 'new-claim',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        app(PerformZoomMeetingRecovery::class)->execute($meeting, 'old-operation', 'old-claim', $zoom);

        $this->assertTrue($zoom->meetingsToDelete->isEmpty());
        $this->assertSame('new-operation', $meeting->fresh()->sync_operation_id);
    }

    /** @test */
    public function it_matches_iso_8601_and_database_formatted_utc_start_time(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Topic',
            'duration' => 30,
            'start_time' => '2026-09-19 08:00:00',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-start-time',
            'sync_payload' => json_encode(['start_time' => '2026-09-19T10:00:00Z']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeetingWithDetails(
            123,
            topic: 'Topic',
            duration: 30,
            startTime: '2026-09-19 10:00:00',
        ));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-start-time', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertEquals('2026-09-19 10:00:00', $meeting->start_time->format('Y-m-d H:i:s'));
        $this->assertTrue($this->zoom->meetingsToUpdate->isEmpty());
    }

    /** @test */
    public function it_matches_numeric_and_boolean_equivalent_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Topic',
            'duration' => 30,
            'join_before_host' => false,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-equiv',
            'sync_payload' => json_encode(['duration' => '45', 'join_before_host' => 'true']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeetingWithDetails(
            123,
            topic: 'Topic',
            duration: 45,
            joinBeforeHost: true,
        ));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-equiv', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertEquals(45, $meeting->duration);
        $this->assertTrue((bool) $meeting->join_before_host);
        $this->assertTrue($this->zoom->meetingsToUpdate->isEmpty());
    }

    /** @test */
    public function a_matching_recovery_saves_the_full_zoom_snapshot_and_a_changed_join_url(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Old Topic',
            'duration' => 30,
            'join_url' => 'https://zoom.us/j/123-old',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-snap',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeetingWithDetails(
            123,
            topic: 'New Topic',
            duration: 45,
            joinUrl: 'https://zoom.us/j/123-refreshed',
        ));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-snap', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertEquals('New Topic', $meeting->topic);
        $this->assertEquals(45, $meeting->duration);
        $this->assertEquals('https://zoom.us/j/123-refreshed', $meeting->join_url);
        $this->assertNull($meeting->sync_operation_id);
    }

    /** @test */
    public function a_mismatching_value_remains_retryable_and_does_not_complete_the_operation(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'topic' => 'Old Topic',
            'duration' => 30,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-mismatch',
            'sync_payload' => json_encode(['topic' => 'Expected New Topic']),
            'sync_claim_token' => 'claim-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'Still Old Topic', 30));
        $this->zoom->shouldFailWithException(new \App\Exceptions\Integrations\Zoom\ZoomRateLimitException(retryAfterSeconds: 60));

        $job = new RecoverZoomMeetingOperationJob($meeting->id, 'op-mismatch', 'claim-token');
        $job->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertEquals('op-mismatch', $meeting->sync_operation_id);
        $this->assertNotNull($meeting->sync_available_at);
        $this->assertTrue($meeting->sync_available_at->isFuture());
    }

    /** @test */
    public function password_recovery_get_confirms_new_password_saves_full_snapshot_and_completes(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 200,
            'password' => 'old-secret',
            'join_url' => 'https://zoom.us/j/200-old',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-200',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        // Zoom already has the new password and a refreshed join_url.
        $this->zoom = $this->fakeZoom()->findsMeeting(new \App\DataTransferObjects\Zoom\Meeting(
            meeting_id: 200,
            topic: 'My Meeting',
            agenda: '',
            created_at: '2026-01-01 00:00:00',
            duration: 30,
            start_time: '2026-01-01 00:00:00',
            start_url: 'https://zoom.us/s/200',
            join_url: 'https://zoom.us/j/200-new',
            status: 'waiting',
            timezone: 'UTC',
            password: 'new-secret',
            join_before_host: false,
            tracking_fields: [],
        ));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-200', 'claim-pw'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        // The operation should be fully cleared.
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame('new-secret', $meeting->password);
        $this->assertSame('https://zoom.us/j/200-new', $meeting->join_url);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertNull($meeting->sync_payload);
        $this->assertNull($meeting->sync_claim_token);
    }

    /** @test */
    public function password_recovery_failed_get_leaves_operation_retryable(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 201,
            'password' => 'old-secret',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-201',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-201',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 0,
        ]);

        // Zoom GET fails with a network error (retryable).
        $this->zoom = $this->fakeZoom()->shouldFailWithException(
            new \App\Exceptions\Integrations\Zoom\ZoomException('Zoom timeout', 503)
        );

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-201', 'claim-pw-201'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        // GET failed — operation stays open for a retry, attempts incremented.
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('op-pw-201', $meeting->sync_operation_id);
        $this->assertSame(1, $meeting->sync_attempts);
        $this->assertNotNull($meeting->sync_available_at);
        $this->assertTrue($meeting->sync_available_at->isFuture());
    }

    /** @test */
    public function password_recovery_get_shows_old_password_sends_patch_and_leaves_pending_for_next_get(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 202,
            'password' => 'old-secret',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-202',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-202',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 0,
        ]);

        // Zoom still shows the old password on the GET.
        $this->zoom = $this->fakeZoom()->findsMeeting(new \App\DataTransferObjects\Zoom\Meeting(
            meeting_id: 202,
            topic: 'My Meeting',
            agenda: '',
            created_at: '2026-01-01 00:00:00',
            duration: 30,
            start_time: '2026-01-01 00:00:00',
            start_url: 'https://zoom.us/s/202',
            join_url: 'https://zoom.us/j/202',
            status: 'waiting',
            timezone: 'UTC',
            password: 'old-secret',  // old password, not new-secret
            join_before_host: false,
            tracking_fields: [],
        ));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-202', 'claim-pw-202'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        // One mismatch cycle was consumed; a PATCH was sent; operation is still pending for the next GET.
        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('op-pw-202', $meeting->sync_operation_id);
        $this->assertSame(1, $meeting->sync_attempts);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNotNull($meeting->sync_available_at);
        $this->assertTrue($meeting->sync_available_at->isFuture());
        // Crucially, the PATCH was sent (not zero updates).
        $this->assertFalse($this->zoom->meetingsToUpdate->isEmpty());
    }

    /** @test */
    public function password_recovery_patch_failure_remains_retryable_and_next_cycle_can_verify(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 206,
            'password' => 'old-secret',
            'join_url' => 'https://zoom.us/j/206-old',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-206',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-206',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 0,
        ]);

        $oldZoomMeeting = $this->zoomMeetingWithDetails(
            id: 206,
            password: 'old-secret',
            joinUrl: 'https://zoom.us/j/206-old',
        );
        $this->zoom = $this->fakeZoom()
            ->findsMeeting($oldZoomMeeting)
            ->failNextUpdateWithException(new \App\Exceptions\Integrations\Zoom\ZoomException('PATCH timeout', 503));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-206', 'claim-pw-206'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertSame(MeetingSyncStatus::Updating, $meeting->sync_status);
        $this->assertSame('op-pw-206', $meeting->sync_operation_id);
        $this->assertSame(2, $meeting->sync_attempts);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNotNull($meeting->sync_available_at);
        $this->assertTrue($meeting->sync_available_at->isFuture());
        $this->assertSame('https://zoom.us/j/206-old', $meeting->join_url);

        $newZoomMeeting = $this->zoomMeetingWithDetails(
            id: 206,
            password: 'new-secret',
            joinUrl: 'https://zoom.us/j/206-new',
        );
        Meeting::query()->whereKey($meeting->getKey())->update([
            'sync_claim_token' => 'claim-pw-206-retry',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => null,
        ]);
        $retryZoom = $this->fakeZoom()->findsMeeting($newZoomMeeting);

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-206', 'claim-pw-206-retry'))
            ->handle($retryZoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame('new-secret', $meeting->password);
        $this->assertSame('https://zoom.us/j/206-new', $meeting->join_url);
        $this->assertNull($meeting->sync_operation_id);
    }

    /** @test */
    public function password_repeated_get_mismatches_reach_max_attempts_and_move_to_update_failed(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 203,
            'password' => 'old-secret',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-203',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-203',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_attempts' => 4, // Already at max - 1.
        ]);

        // Zoom still shows the old password — this is the last allowed mismatch cycle.
        $this->zoom = $this->fakeZoom()->findsMeeting(new \App\DataTransferObjects\Zoom\Meeting(
            meeting_id: 203,
            topic: 'My Meeting',
            agenda: '',
            created_at: '2026-01-01 00:00:00',
            duration: 30,
            start_time: '2026-01-01 00:00:00',
            start_url: 'https://zoom.us/s/203',
            join_url: 'https://zoom.us/j/203',
            status: 'waiting',
            timezone: 'UTC',
            password: 'old-secret',
            join_before_host: false,
            tracking_fields: [],
        ));

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-203', 'claim-pw-203'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        // Max cycles reached — transition to UpdateFailed.
        $this->assertSame(MeetingSyncStatus::UpdateFailed, $meeting->sync_status);
        // Operation ID and payload are retained for manual review.
        $this->assertSame('op-pw-203', $meeting->sync_operation_id);
        $this->assertNotNull($meeting->sync_payload);
        $this->assertSame('max_attempts_reached', $meeting->sync_error);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_lease_expires_at);
        $this->assertNull($meeting->sync_available_at);
        // No PATCH was sent because we stopped before sending it.
        $this->assertTrue($this->zoom->meetingsToUpdate->isEmpty());
    }

    /** @test */
    public function webhook_completing_meeting_while_password_patch_in_flight_remains_authoritative(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 204,
            'password' => 'old-secret',
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-204',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-204',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_started_at' => now()->subSeconds(30),
            'last_zoom_event_timestamp' => null,
        ]);
        $this->zoom = $this->fakeZoom()->findsMeeting($this->zoomMeetingWithDetails(
            id: 204,
            password: 'old-secret',
            joinUrl: 'https://zoom.us/j/204-old',
        ));
        $this->zoom->beforeUpdatingMeeting(function (): void {
            $this->zoom->findsMeeting($this->zoomMeetingWithDetails(
                id: 204,
                password: 'new-secret',
                joinUrl: 'https://zoom.us/j/204-new',
            ));

            // This runs inside ZoomServiceFake::updateMeeting(), before the PATCH returns.
            app(HandleMeetingUpdatedWebhook::class)->handle(
                new MeetingUpdatedWebhookData(
                    meetingId: 204,
                    changes: ['password' => 'new-secret', 'join_url' => 'https://zoom.us/j/204-new'],
                    requestId: 'pw-webhook-204',
                ),
                (int) now()->addSecond()->valueOf(),
            );
        });

        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-204', 'claim-pw-204'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();

        // The webhook completed while PATCH was in flight; the late response must not restore it.
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertNull($meeting->sync_operation_id);
        $this->assertSame('new-secret', $meeting->password);
        $this->assertSame('https://zoom.us/j/204-new', $meeting->join_url);
        $this->assertCount(1, $this->zoom->meetingsToUpdate);
    }

    /** @test */
    public function delete_webhook_while_password_patch_in_flight_is_authoritative(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 205,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-pw-205',
            'sync_payload' => json_encode(['password' => 'new-secret']),
            'sync_claim_token' => 'claim-pw-205',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'last_zoom_event_timestamp' => null,
        ]);

        // Delete webhook arrives and marks the meeting as deleted.
        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData(205),
            (int) now()->subSeconds(5)->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);

        // Recovery job that arrives later must be skipped (claim token cleared by delete webhook).
        (new RecoverZoomMeetingOperationJob($meeting->id, 'op-pw-205', 'claim-pw-205'))
            ->handle($this->zoom, app(PerformZoomMeetingRecovery::class));

        $meeting->refresh();
        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->sync_status);
    }

    /** @test */
    public function recovery_job_has_90_second_timeout(): void
    {
        $job = new RecoverZoomMeetingOperationJob(1, 'op-test', 'claim-test');
        $this->assertSame(90, $job->timeout);
    }

    private function zoomMeetingWithDetails(
        int $id,
        string $topic = 'Topic',
        int $duration = 30,
        string $startTime = '2026-09-19 10:00:00',
        string $joinUrl = '',
        bool $joinBeforeHost = false,
        string $password = '',
    ): \App\DataTransferObjects\Zoom\Meeting {
        return new \App\DataTransferObjects\Zoom\Meeting(
            meeting_id: $id,
            topic: $topic,
            agenda: '',
            created_at: '2026-09-19 10:00:00',
            duration: $duration,
            start_time: $startTime,
            start_url: "https://zoom.us/s/{$id}",
            join_url: $joinUrl !== '' ? $joinUrl : "https://zoom.us/j/{$id}",
            status: 'waiting',
            timezone: 'UTC',
            password: $password,
            join_before_host: $joinBeforeHost,
            tracking_fields: [],
        );
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
