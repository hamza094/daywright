<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
                Mockery::on(function (array $context) use ($token): bool {
                    $json = json_encode($context);

                    return ! str_contains($json, $token) && isset($context['user_uuid']);
                })
            );

        $job = new QueuedPasswordResetJob($user->id, $token);
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
                Mockery::on(fn (array $context): bool => isset($context['user_uuid']) && $context['user_uuid'] === $user->uuid)
            );

        $job = new QueuedVerifyEmailJob($user->id);
        $job->failed(new RuntimeException('Simulated failure'));

        $this->assertTrue(true);
    }

    /** @test */
    public function password_reset_job_implements_should_be_encrypted(): void
    {
        $user = User::factory()->create();
        $token = 'super-secret-reset-token-12345';

        $job = new QueuedPasswordResetJob($user->id, $token);

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
    }

    /** @test */
    public function password_reset_job_encrypts_payload_in_database_and_decrypts_on_worker_execution(): void
    {
        Notification::fake();
        config(['queue.default' => 'database']);

        $user = User::factory()->create();
        $token = 'super-secret-sentinel-token';

        QueuedPasswordResetJob::dispatch($user->id, $token);

        // 1. Assert plaintext token is never stored in the database jobs table
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertNotEmpty($payload);
        $this->assertStringNotContainsString($token, $payload);

        // 2. Process the job and verify worker decrypts the token for the notification
        Queue::pop('critical')->fire();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            fn (ResetPassword $notification): bool => $notification->token === $token
        );
    }
}
