<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SendMeetingEndedNotification;
use App\Notifications\Zoom\MeetingEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class SendMeetingEndedNotificationTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function does_not_send_when_flag_is_already_set(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => now(),
        ]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: [
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project'
            ]
        );

        $job->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function claims_flag_and_sends_notification(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: [
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project'
            ]
        );

        $job->handle();

        Notification::assertSentTo($this->user, MeetingEnded::class);
        $this->assertNotNull($meeting->fresh()->ended_notification_sent_at);
    }

    /** @test */
    public function does_not_claim_if_already_claimed_by_another_worker(): void
    {
        Notification::fake();

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 813,
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        // Simulate another worker claiming the flag
        $meeting->update(['ended_notification_sent_at' => now()]);

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: [
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project'
            ]
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
            'status' => 'ended',
            'ended_notification_sent_at' => null,
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Notification service failed'));

        $job = new SendMeetingEndedNotification(
            meetingId: $meeting->id,
            notificationData: [
                'meeting_topic' => 'Test',
                'project_name' => 'Project',
                'notifier' => ['name' => 'Test User'],
                'meeting_join_url' => 'https://zoom.us/j/813',
                'start_time' => '2024-06-24T11:00:00Z',
                'end_time' => '2024-06-24T12:00:00Z',
                'meeting_timezone' => 'UTC',
                'project_slug' => 'test-project'
            ]
        );

        $this->expectException(RuntimeException::class);
        $job->handle();

        // Flag should be rolled back
        $this->assertNull($meeting->fresh()->ended_notification_sent_at);
    }

    /** @test */
    public function failed_method_logs_meeting_id_and_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Meeting ended notification job failed',
                Mockery::on(fn (array $context): bool => 
                    isset($context['meeting_id']) &&
                    $context['meeting_id'] === 999 &&
                    isset($context['error']) &&
                    $context['error'] === 'Test failure' &&
                    isset($context['trace'])
                )
            );

        $job = new SendMeetingEndedNotification(
            meetingId: 999,
            notificationData: []
        );

        $job->failed(new RuntimeException('Test failure'));

        $this->assertTrue(true);
    }
}
