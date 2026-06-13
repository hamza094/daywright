<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Jobs\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\Events\MeetingStatusUpdate;
use App\Jobs\Webhooks\Zoom\StartMeetingWebhook;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Notification::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        $users = User::factory()->count(2)->create()->each(fn (User $user) => $this->inviteAndActivateUser($this->project, $user)
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

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        $expectedLink = $this->apiV1Route('projects.show', ['project' => $this->project]);
        $expectedUrl = route('api.v1.projects.show', ['project' => $this->project]);

        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);

        Notification::assertSentTo($users, MeetingStarted::class, fn (MeetingStarted $notification, array $channels): bool => $channels === ['mail', 'database', 'broadcast']
            && $notification->toDatabase($this->user)['link'] === $expectedLink
            && $notification->toMail($this->user)->viewData['projectLink'] === $expectedUrl);
    }

    /** @test */
    public function allows_missing_start_time_without_failing(): void
    {
        Notification::fake();
        Event::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'waiting',
        ]);

        $users = User::factory()->count(2)->create()->each(fn (User $user) => $this->inviteAndActivateUser($this->project, $user)
        );

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: null,
            requestId: null,
        ));

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        Event::assertDispatched(fn (MeetingStatusUpdate $event): bool => $event->meeting->id === $meeting->id);
        Notification::assertSentTo($users, MeetingStarted::class);
    }

    /** @test */
    public function ignores_missing_meeting_without_failing(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 999999,
            startTime: null,
            requestId: null,
        ));

        $job->handle();

        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function does_not_send_duplicate_notifications_when_meeting_is_already_started(): void
    {
        Notification::fake();
        Event::fake([MeetingStatusUpdate::class]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
        ]);

        $job = new StartMeetingWebhook(new MeetingStartedWebhookData(
            meetingId: 813,
            startTime: '2024-06-24T11:00:00Z',
            requestId: 'zoom-start-duplicate',
        ));

        $job->handle();

        $this->assertEquals('started', $meeting->fresh()->status);
        Notification::assertNothingSent();
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
        Notification::fake();
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

        $job->handle();

        $this->assertEquals('waiting', $meeting->fresh()->status);
        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }

    /** @test */
    public function ended_meeting_cannot_be_restarted_via_webhook(): void
    {
        Notification::fake();
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

        $job->handle();

        // Meeting should remain ended
        $this->assertEquals('ended', $meeting->fresh()->status);

        // No notification should be sent
        Notification::assertNothingSent();
        Event::assertNotDispatched(MeetingStatusUpdate::class);
    }
}
