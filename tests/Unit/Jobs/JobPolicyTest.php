<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\MailMessage;
use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Jobs\SmsMessage;
use Tests\TestCase;

class JobPolicyTest extends TestCase
{
    /** @test */
    public function mail_message_has_expected_job_policies(): void
    {
        $job = new MailMessage(1, 1, 1, 'test-uuid');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertEquals([30, 120], $job->backoff);
    }

    /** @test */
    public function sms_message_has_expected_job_policies(): void
    {
        $job = new SmsMessage(1, 1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertEquals([30, 120], $job->backoff);
    }

    /** @test */
    public function queued_password_reset_job_has_expected_job_policies(): void
    {
        $user = \App\Models\User::factory()->make();
        $job = new QueuedPasswordResetJob($user, 'token');

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(30, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
    }

    /** @test */
    public function queued_verify_email_job_has_expected_job_policies(): void
    {
        $user = \App\Models\User::factory()->make();
        $job = new QueuedVerifyEmailJob($user);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(30, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
    }
}
