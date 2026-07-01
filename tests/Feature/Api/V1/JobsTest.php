<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\CancelZoomMeetingsJob;
use App\Jobs\MailMessage;
use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Jobs\SendMeetingEndedNotification;
use App\Jobs\SendMeetingStartedNotification;
use App\Jobs\SmsMessage;
use App\Mail\ProjectMail;
use App\Models\Message;
use App\Models\User;
use App\Services\VonageSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class JobsTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /**
     * Job Tests.
     */

    /** @test */
    public function send_mail_job(): void
    {
        $message = Message::factory()->for($this->project)
            ->create([
                'subject' => 'thus is project subject',
                'message' => 'this is project message',
                'type' => 'mail',
                'delivered' => false,
            ]);

        $message->users()->attach($this->user);

        $job = new MailMessage($this->project->id, $message->id, $this->user->id, $this->user->uuid);

        Mail::fake();

        $job->handle();

        Mail::assertSent(ProjectMail::class);
    }

    /** @test */
    public function mock_send_sms_job(): void
    {
        $project = $this->project;

        $message = Message::factory()->for($project)->
          create([
              'message' => 'this is project message',
              'type' => 'sms',
              'delivered' => false,
          ]);

        $message->users()->attach($this->user);

        $mock = $this->mock(VonageSmsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')
                ->once()
            // ->with($project,$message)
                ->andReturn('https://picsum.photos/200/300');
        });

        $job = new SmsMessage($project->id, $message->id);
        $job->handle($mock);
    }

    /** @test */
    public function password_reset_job_uses_critical_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = new QueuedPasswordResetJob($user, 'test-token');

        dispatch($job);

        Queue::assertPushed(QueuedPasswordResetJob::class, function ($job) {
            return $job->queue === 'critical';
        });
    }

    /** @test */
    public function verify_email_job_uses_critical_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = new QueuedVerifyEmailJob($user);

        dispatch($job);

        Queue::assertPushed(QueuedVerifyEmailJob::class, function ($job) {
            return $job->queue === 'critical';
        });
    }

    /** @test */
    public function mail_message_job_uses_default_queue(): void
    {
        Queue::fake();

        $message = Message::factory()->for($this->project)->create();
        $job = new MailMessage($this->project->id, $message->id, $this->user->id, $this->user->uuid);

        dispatch($job);

        Queue::assertPushed(MailMessage::class, function ($job) {
            return $job->queue === 'default';
        });
    }

    /** @test */
    public function sms_message_job_uses_default_queue(): void
    {
        Queue::fake();

        $message = Message::factory()->for($this->project)->create();
        $job = new SmsMessage($this->project->id, $message->id);

        dispatch($job);

        Queue::assertPushed(SmsMessage::class, function ($job) {
            return $job->queue === 'default';
        });
    }

    /** @test */
    public function meeting_started_notification_job_uses_default_queue(): void
    {
        Queue::fake();

        $job = new SendMeetingStartedNotification(1, []);

        dispatch($job);

        Queue::assertPushed(SendMeetingStartedNotification::class, function ($job) {
            return $job->queue === 'default';
        });
    }

    /** @test */
    public function meeting_ended_notification_job_uses_default_queue(): void
    {
        Queue::fake();

        $job = new SendMeetingEndedNotification(1, []);

        dispatch($job);

        Queue::assertPushed(SendMeetingEndedNotification::class, function ($job) {
            return $job->queue === 'default';
        });
    }

    /** @test */
    public function cancel_zoom_meetings_job_uses_default_queue(): void
    {
        Queue::fake();

        $job = new CancelZoomMeetingsJob([]);

        dispatch($job);

        Queue::assertPushed(CancelZoomMeetingsJob::class, function ($job) {
            return $job->queue === 'default';
        });
    }
}
