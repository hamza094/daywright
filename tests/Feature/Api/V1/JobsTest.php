<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\SmsMessage;
use App\Mail\ProjectMail;
use App\Models\Message;
use App\Services\VonageSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

        $job = new \App\Jobs\MailMessage($this->project, $message,
            $this->user);

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

        app(SmsMessage::class)->handle($mock);
    }
}
