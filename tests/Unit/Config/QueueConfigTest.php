<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    /** @test */
    public function queue_retry_after_is_greater_than_all_job_timeouts(): void
    {
        $retryAfter = config('queue.connections.database.retry_after');
        $redisRetryAfter = config('queue.connections.redis.retry_after');

        // All job timeouts should be less than retry_after
        // MailMessage: 60, SmsMessage: 60, QueuedPasswordResetJob: 30, QueuedVerifyEmailJob: 30
        // CancelZoomMeetingsJob: 60, ZoomMeetingWebhookJob: 60
        // SendMeetingStartedNotification: 60, SendMeetingEndedNotification: 60
        $maxJobTimeout = 60;

        $this->assertGreaterThan($maxJobTimeout, $retryAfter, 'Database retry_after must be greater than max job timeout');
        $this->assertGreaterThan($maxJobTimeout, $redisRetryAfter, 'Redis retry_after must be greater than max job timeout');
    }

    /** @test */
    public function database_and_redis_have_after_commit_enabled(): void
    {
        $databaseAfterCommit = config('queue.connections.database.after_commit');
        $redisAfterCommit = config('queue.connections.redis.after_commit');

        $this->assertTrue($databaseAfterCommit, 'Database connection should have after_commit enabled');
        $this->assertTrue($redisAfterCommit, 'Redis connection should have after_commit enabled');
    }
}
