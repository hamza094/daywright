<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Channels\DatabaseChannel;
use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\User;
use App\Notifications\Zoom\MeetingStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class SendMeetingStartedNotificationTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function does_not_send_when_flag_is_already_set(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => now(),
        ]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function claims_flag_and_sends_notification(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
            'started_notification_pending_at' => now(),
        ]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertSentTo($assignee, MeetingStarted::class);
        $meeting = $meeting->fresh();
        $this->assertNotNull($meeting->started_notification_sent_at);
        $this->assertNull($meeting->started_notification_pending_at);
    }

    /** @test */
    public function does_not_claim_if_already_claimed_by_another_worker(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
        ]);

        // Simulate another worker claiming the flag
        $meeting->update(['started_notification_sent_at' => now()]);

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $job->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function rolls_back_flag_on_notification_send_failure(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'started',
            'started_notification_sent_at' => null,
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Notification service failed'));

        $job = new SendMeetingStartedNotification(
            meetingId: $meeting->id,
            notificationData: MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $exceptionThrown = false;
        try {
            $job->handle();
        } catch (RuntimeException) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'RuntimeException should be thrown');

        // Flag should be rolled back
        $this->assertNull($meeting->fresh()->started_notification_sent_at);
    }

    /** @test */
    public function failed_method_logs_meeting_id_and_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Meeting started notification job failed',
                Mockery::on(fn (array $context): bool => $context['meeting_id'] === 999 &&
                    $context['exception_class'] === RuntimeException::class &&
                    $context['exception_code'] === 0 &&
                    ! array_key_exists('exception', $context))
            );

        $job = new SendMeetingStartedNotification(
            meetingId: 999,
            notificationData: MeetingNotificationData::fromArray([])
        );

        $job->failed(new RuntimeException('Test failure'));

        $this->assertTrue(true);
    }

    /** @test */
    public function meeting_started_notification_does_not_implement_should_queue(): void
    {
        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([])
        );

        $this->assertNotInstanceOf(
            ShouldQueue::class,
            $notification,
            'MeetingStarted must not implement ShouldQueue to prevent nested queuing marking notifications sent prematurely.'
        );
    }

    /** @test */
    public function does_not_queue_downstream_notification_jobs(): void
    {
        Queue::fake();

        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        Notification::send($assignee, $notification);

        Queue::assertNotPushed(SendQueuedNotifications::class);
    }

    /** @test */
    public function meeting_started_notification_uses_custom_database_channel(): void
    {
        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([])
        );

        $channels = $notification->via(new User);

        $this->assertContains(DatabaseChannel::class, $channels);
        $this->assertNotContains('database', $channels);
    }

    /** @test */
    public function database_channel_deduplicates_notifications_on_retry(): void
    {
        $assignee = User::factory()->create();
        $this->inviteAndActivateUser($this->project, $assignee);

        $notification = new MeetingStarted(
            MeetingNotificationData::fromArray([
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project',
                'end_time' => null,
            ])
        );

        $channel = new DatabaseChannel;
        $channel->send($assignee, $notification);
        $channel->send($assignee, $notification);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $assignee->id,
            'type' => MeetingStarted::class,
        ]);
    }
}
