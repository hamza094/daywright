<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QueuedAuthJobsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function password_reset_job_failed_method_does_not_log_token(): void
    {
        $user = User::factory()->create();
        $token = 'super-secret-reset-token-12345';

        Log::shouldReceive('error')
            ->once()
            ->with(
                'QueuedPasswordResetJob failed',
                Mockery::on(function (array $context) use ($token) {
                    $json = json_encode($context);
                    return !str_contains($json, $token) && isset($context['user_uuid']);
                })
            );

        $job = new QueuedPasswordResetJob($user, $token);
        $job->failed(new RuntimeException('Simulated failure'));

        // Assert true because the mock expectation does the real asserting
        $this->assertTrue(true);
    }

    /** @test */
    public function verify_email_job_failed_method_logs_safely(): void
    {
        $user = User::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->with(
                'QueuedVerifyEmailJob failed',
                Mockery::on(fn (array $context) => isset($context['user_uuid']) && $context['user_uuid'] === $user->uuid)
            );

        $job = new QueuedVerifyEmailJob($user);
        $job->failed(new RuntimeException('Simulated failure'));

        $this->assertTrue(true);
    }
}
