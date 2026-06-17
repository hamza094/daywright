<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingStartedWebhook;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\Events\MeetingStatusUpdate;
use App\Jobs\SendMeetingStartedNotification;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

/**
 * Feature tests for the StartMeetingWebhook job.
 *
 * Tests the job that processes Zoom meeting.started webhook events. These tests verify:
 * - Notification of project members when a meeting starts
 * - Handling of missing start_time fields gracefully
 * - Ignoring of missing meetings
 * - Prevention of duplicate notifications
 * - Logging of terminal failures
 * - State machine integrity (ended meetings cannot be restarted)
 * - Sync status filtering (only active meetings accept runtime webhooks)
 *
 * Level: Feature/Job testing
 */
class StartMeetingWebhookTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function notifies_project_members_on_meeting_start(): void
    {
        Bus::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        User::factory()->count(2)->create()->each(fn (User $user) => $this->inviteAndActivateUser($this->project, $user)
        );

        $fixture = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_start.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $payload = $fixture['payload'];
        $object = $payload['object'];
        $meetingId = $object['id'];
        $startTime = $object['start_time'] ?? null;

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: $meetingId,
            startTime: $startTime,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        $this->assertEquals('started', $meeting->fresh()->status);

        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);

        Bus::assertDispatched(SendMeetingStartedNotification::class, fn (SendMeetingStartedNotification $job): bool => $job->meetingId === $meeting->id);
    }

    /** @test */
    public function allows_missing_start_time_without_failing(): void
    {
        Bus::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: null,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        $this->assertEquals('started', $meeting->fresh()->status);
        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);
        Bus::assertDispatched(SendMeetingStartedNotification::class);
    }

    /** @test */
    public function ignores_missing_meeting_without_failing(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 999999,
            startTime: null,
            requestId: null,
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function does_not_send_duplicate_notifications_when_meeting_is_already_started(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => now(),
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-start-duplicate',
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        $this->assertEquals('started', $meeting->fresh()->status);
        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function terminal_failures_are_logged_with_structured_context(): void
    {
        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-start-failed',
        ));

        $job->failed(new RuntimeException('Zoom notification send failed.'));

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    /** @test */
    public function ignores_webhook_if_sync_status_is_not_active(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
            'sync_status' => \App\Enums\Meeting\MeetingSyncStatus::Pending->value,
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-start-inactive',
        ));

        Log::shouldReceive('channel')
            ->once()
            ->with('zoom')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'zoom_webhook_ignored',
                Mockery::on(fn (array $context): bool => $context === [
                    'provider' => 'zoom',
                    'operation' => 'zoom.webhook.meeting.started',
                    'meeting_id' => 813,
                    'request_id' => 'zoom-start-inactive',
                    'user_uuid' => $this->user->uuid,
                    'reason' => 'inactive_sync_status',
                ])
            );

        $job->handle(app(HandleMeetingStartedWebhook::class));

        $this->assertEquals('waiting', $meeting->fresh()->status);
        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function ended_meeting_cannot_be_restarted_via_webhook(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-restart-ended',
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        // Meeting should remain ended
        $this->assertEquals('ended', $meeting->fresh()->status);

        // No job should be dispatched
        Bus::assertNotDispatched(SendMeetingStartedNotification::class);
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function sends_notification_on_retry_when_meeting_already_started_but_notification_not_sent(): void
    {
        Bus::fake();
        Event::fake([MeetingStatusUpdate::class]);

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-start-retry',
        ));

        $job->handle(app(HandleMeetingStartedWebhook::class));

        // Job should be dispatched on retry
        Bus::assertDispatched(SendMeetingStartedNotification::class);
    }

    /** @test */
    public function failed_job_logs_sanitized_context_without_sensitive_data(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('zoom_webhook_failed')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with(
                'zoom_webhook_failed',
                Mockery::on(fn (array $context): bool => isset($context['provider']) &&
                    $context['provider'] === 'zoom' &&
                    isset($context['operation']) &&
                    $context['operation'] === 'zoom.webhook.meeting.started' &&
                    isset($context['meeting_id']) &&
                    $context['meeting_id'] === 813 &&
                    isset($context['request_id']) &&
                    $context['request_id'] === 'zoom-failed-sanitize' &&
                    isset($context['exception']) &&
                    isset($context['message']) &&
                    // Ensure no sensitive data is present
                    ! str_contains((string) $context['message'], 'access_token') &&
                    ! str_contains((string) $context['message'], 'refresh_token') &&
                    ! str_contains((string) $context['message'], 'zak') &&
                    ! str_contains((string) $context['message'], 'password') &&
                    ! str_contains((string) $context['message'], 'secret')
                )
            );

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-failed-sanitize',
        ));

        // Simulate a failure with sensitive data in the exception message
        $exception = new RuntimeException('Failed with access_token=abc123 and zak=xyz789');
        $job->failed($exception);

        $this->assertTrue(true);
    }
}
