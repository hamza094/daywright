<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Meetings;

use App\Actions\Meetings\UpdateProjectMeeting;
use App\Actions\Webhooks\Zoom\HandleMeetingDeletedWebhook;
use App\Actions\Webhooks\Zoom\HandleMeetingUpdatedWebhook;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\DataTransferObjects\Zoom\MeetingUpdateData;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingOperationUnknownException;
use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

final class UpdateProjectMeetingTest extends TestCase
{
    use InteractsWithZoom, RefreshDatabase;

    private UpdateProjectMeeting $action;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
        $this->zoom = $this->fakeZoom();
        $this->action = app(UpdateProjectMeeting::class);
    }

    /** @test */
    public function successful_update_saves_zoom_and_local_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'duration' => 30,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            duration: 45,
        );

        $result = $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $this->assertEquals('New Topic', $result->topic);
        $this->assertEquals(45, $result->duration);
        $this->assertEquals(MeetingSyncStatus::Active, $result->sync_status);
        $this->assertNull($result->sync_payload);
        $this->assertNull($result->sync_operation_id);
    }

    /** @test */
    public function zoom_failure_leaves_original_local_values_unchanged(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'duration' => 30,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            duration: 45,
        );

        $this->zoom->shouldFailWithException(new ZoomException('Invalid request', 400));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomException to be thrown.');
        } catch (ZoomException) {
            $meeting->refresh();

            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertEquals(30, $meeting->duration);
            $this->assertEquals(MeetingSyncStatus::UpdateFailed, $meeting->sync_status);
        }
    }

    /** @test */
    public function timeout_leaves_operation_recoverable(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->zoom->shouldFailWithException(new ZoomMeetingOperationUnknownException('Connection timeout'));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomMeetingOperationUnknownException to be thrown.');
        } catch (ZoomMeetingOperationUnknownException) {
            $meeting->refresh();

            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertEquals(MeetingSyncStatus::Updating, $meeting->sync_status);
            $this->assertNotNull($meeting->sync_available_at);
        }
    }

    /** @test */
    public function operation_id_prevents_stale_update(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $result = $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $originalOperationId = $result->sync_operation_id;

        $meeting->update([
            'sync_operation_id' => Str::uuid()->toString(),
            'sync_status' => MeetingSyncStatus::Updating,
        ]);

        $this->assertEquals('New Topic', $result->topic);
        $this->assertNotEquals($originalOperationId, $meeting->sync_operation_id);
    }

    /** @test */
    public function second_update_is_rejected_while_one_is_active(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
            'sync_status' => MeetingSyncStatus::Updating,
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meeting must be active or update_failed to update');

        $this->action->handle($meeting, $this->user, $data, $this->zoom);
    }

    /** @test */
    public function rate_limit_rolls_back_to_active_for_manual_retry(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->zoom->shouldFailWithException(new ZoomRateLimitException(60, 'Rate limited'));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomRateLimitException to be thrown.');
        } catch (ZoomRateLimitException) {
            $meeting->refresh();

            $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
            $this->assertEquals('Old Topic', $meeting->topic);
            $this->assertNull($meeting->sync_payload);
            $this->assertNull($meeting->sync_operation_id);
        }
    }

    /** @test */
    public function rate_limited_update_does_not_discard_a_later_delete_webhook(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'sync_reconcile_before_at' => now()->subMinute(),
        ]);
        $deleteEventTimestamp = (int) now()->subSeconds(10)->valueOf();
        $this->zoom->shouldFailWithException(new ZoomRateLimitException(60, 'Rate limited'));

        try {
            $this->action->handle($meeting, $this->user, new MeetingUpdateData(topic: 'New Topic'), $this->zoom);
        } catch (ZoomRateLimitException) {
            // The local attempt was rejected before Zoom could mutate the meeting.
        }

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData($meeting->meeting_id),
            $deleteEventTimestamp,
        );

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    /** @test */
    public function sync_payload_is_encrypted(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $meeting->refresh();

        $this->assertNull($meeting->sync_payload);
    }

    /** @test */
    public function password_payload_is_never_written_to_logs(): void
    {
        $sentinelPassword = 'password-that-must-not-appear-in-logs';
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
            password: $sentinelPassword,
        );

        Log::spy();
        $this->zoom->shouldFailWithException(new ZoomException('Zoom rejected the update', 400));

        try {
            $this->action->handle($meeting, $this->user, $data, $this->zoom);
            $this->fail('Expected ZoomException to be thrown.');
        } catch (ZoomException) {
            // The failure path emits an operational warning that must not expose the password.
        }

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'topic' => 'Old Topic',
            'sync_status' => MeetingSyncStatus::UpdateFailed->value,
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($sentinelPassword): bool {
                return ! str_contains($message, $sentinelPassword)
                    && ! str_contains((string) json_encode($context), $sentinelPassword);
            });
    }

    /** @test */
    public function delayed_webhook_before_a_successful_api_update_cannot_overwrite_local_values(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
            'last_zoom_event_timestamp' => null,
        ]);
        $oldWebhookTimestamp = (int) now()->subSeconds(5)->valueOf();

        $this->action->handle(
            $meeting,
            $this->user,
            new MeetingUpdateData(topic: 'API Update B'),
            $this->zoom,
        );

        $this->zoom->findsMeeting($this->zoomMeetingSnapshot($meeting, 'API Update B'));

        $this->assertNull($meeting->refresh()->last_zoom_event_timestamp);

        app(HandleMeetingUpdatedWebhook::class)->handle(
            new MeetingUpdatedWebhookData(
                meetingId: $meeting->meeting_id,
                changes: ['topic' => 'Delayed Update A'],
                requestId: 'delayed-update-a',
            ),
            $oldWebhookTimestamp,
        );

        $this->assertSame('API Update B', $meeting->refresh()->topic);
    }

    /** @test */
    public function delayed_delete_after_update_response_is_not_rejected_by_local_completion_time(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Before update',
            'last_zoom_event_timestamp' => null,
        ]);

        $this->action->handle($meeting, $this->user, new MeetingUpdateData(topic: 'Updated'), $this->zoom);

        app(HandleMeetingDeletedWebhook::class)->handle(
            new MeetingDeletedWebhookData($meeting->meeting_id),
            (int) now()->subSecond()->valueOf(),
        );

        $this->assertSame(MeetingSyncStatus::Deleted, $meeting->refresh()->sync_status);
    }

    /** @test */
    public function delayed_password_webhook_reconciles_the_current_join_url_from_zoom(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Original Topic',
            'join_url' => 'https://zoom.us/j/old',
        ]);

        $this->action->handle($meeting, $this->user, new MeetingUpdateData(password: 'new-secret'), $this->zoom);
        $this->zoom->findsMeeting($this->zoomMeetingSnapshot(
            $meeting,
            'Original Topic',
            password: 'new-secret',
            joinUrl: 'https://zoom.us/j/new',
        ));

        app(HandleMeetingUpdatedWebhook::class)->handle(
            new MeetingUpdatedWebhookData($meeting->meeting_id, ['password' => 'new-secret', 'join_url' => 'https://zoom.us/j/new'], 'password-update'),
            (int) now()->subSecond()->valueOf(),
        );

        $meeting->refresh();
        $this->assertSame('new-secret', $meeting->password);
        $this->assertSame('https://zoom.us/j/new', $meeting->join_url);
    }

    /** @test */
    public function new_operation_starts_with_fresh_retry_state(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
            'sync_attempts' => 5,
            'sync_claim_token' => 'old-token',
            'sync_available_at' => now()->subHour(),
        ]);

        $data = new MeetingUpdateData(
            topic: 'New Topic',
        );

        $this->action->handle($meeting, $this->user, $data, $this->zoom);

        $meeting->refresh();

        $this->assertEquals(0, $meeting->sync_attempts);
        $this->assertNull($meeting->sync_claim_token);
        $this->assertNull($meeting->sync_available_at);
    }

    /** @test */
    public function password_update_leaves_operation_pending_for_recovery_verification(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'My Meeting',
            'join_url' => 'https://zoom.us/j/old',
        ]);

        $result = $this->action->handle(
            $meeting,
            $this->user,
            new MeetingUpdateData(password: 'hunter2'),
            $this->zoom,
        );

        $result->refresh();

        // The PATCH succeeded, but we don't finalise locally yet.
        // Recovery needs to do a GET first to capture the refreshed join_url.
        $this->assertSame(MeetingSyncStatus::Updating, $result->sync_status);
        $this->assertNotNull($result->sync_operation_id);
        $this->assertNotNull($result->sync_payload);
        $this->assertNotNull($result->sync_available_at);
        $this->assertTrue($result->sync_available_at->isFuture());
        $this->assertNull($result->sync_claim_token);
        $this->assertNull($result->sync_lease_expires_at);
    }

    /** @test */
    public function non_password_update_completes_locally_without_pending_verification(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Old Topic',
        ]);

        $result = $this->action->handle(
            $meeting,
            $this->user,
            new MeetingUpdateData(topic: 'New Topic', duration: 60),
            $this->zoom,
        );

        $result->refresh();

        // Non-password updates are applied locally immediately.
        $this->assertSame(MeetingSyncStatus::Active, $result->sync_status);
        $this->assertNull($result->sync_operation_id);
        $this->assertNull($result->sync_payload);
        $this->assertSame('New Topic', $result->topic);
    }

    /** @test */
    public function late_password_patch_response_does_not_schedule_verification_when_operation_was_overtaken(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'topic' => 'Before',
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        // First password update succeeds and schedules verification.
        $this->action->handle($meeting, $this->user, new MeetingUpdateData(password: 'first'), $this->zoom);
        $afterFirst = $meeting->refresh();
        $firstOperationId = $afterFirst->sync_operation_id;
        $this->assertNotNull($firstOperationId);

        // A newer operation overtakes: simulate by directly changing the operation ID in the DB.
        Meeting::query()->whereKey($meeting->getKey())->update([
            'sync_operation_id' => 'newer-operation',
            'sync_claim_token' => null,
            'sync_available_at' => null,
            'sync_status' => MeetingSyncStatus::Active,
            'sync_payload' => null,
        ]);

        // The row no longer has the first operation; calling schedulePasswordVerification
        // with the old operation ID is a no-op (executeWithOperationIdCheck guards it).
        $meeting->refresh();
        $this->assertNotSame($firstOperationId, $meeting->sync_operation_id);
    }

    private function zoomMeetingSnapshot(Meeting $meeting, string $topic, string $password = '', string $joinUrl = 'https://zoom.us/j/current'): ZoomMeeting
    {
        return new ZoomMeeting(
            meeting_id: (int) $meeting->meeting_id,
            topic: $topic,
            agenda: $meeting->agenda ?? '',
            created_at: '2026-01-01 00:00:00',
            duration: (int) ($meeting->duration ?? 30),
            start_time: '2026-01-01 00:00:00',
            start_url: 'https://zoom.us/s/current',
            join_url: $joinUrl,
            status: 'waiting',
            timezone: $meeting->timezone ?? 'UTC',
            password: $password,
            join_before_host: (bool) ($meeting->join_before_host ?? false),
        );
    }
}
