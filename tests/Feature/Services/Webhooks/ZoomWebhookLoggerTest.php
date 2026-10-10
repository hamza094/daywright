<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Webhooks;

use App\Services\Webhooks\ZoomWebhookLogger;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ZoomWebhookLoggerTest extends TestCase
{
    /** @test */
    public function webhook_retry_scheduled_does_not_leak_exception_message(): void
    {
        $logger = new ZoomWebhookLogger;

        // Create an exception with a fake credential in the message
        $exception = new RuntimeException('Authorization: Bearer FAKE_P19_REVIEW_SECRET');

        Log::shouldReceive('channel')->with('zoom')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'zoom_webhook_retry_scheduled',
                Mockery::on(function (array $context): bool {
                    // Verify the fake credential is NOT in the context
                    $contextJson = json_encode($context);

                    return ! str_contains($contextJson, 'FAKE_P19_REVIEW_SECRET')
                        && ! str_contains($contextJson, 'Authorization: Bearer')
                        // Verify safe identifiers ARE in the context
                        && isset($context['meeting_id'])
                        && $context['meeting_id'] === 12345
                        && isset($context['request_id'])
                        && $context['request_id'] === 'req-abc-123'
                        && isset($context['user_id'])
                        && $context['user_id'] === 'user-xyz'
                        // Verify exception class and code are present
                        && isset($context['exception'])
                        && $context['exception'] === RuntimeException::class
                        && isset($context['exception_code'])
                        && $context['exception_code'] === 0
                        // Verify message field is absent
                        && ! array_key_exists('message', $context);
                })
            );

        $logger->logWebhookRetryScheduled(
            operation: 'meeting.created',
            meetingId: 12345,
            requestId: 'req-abc-123',
            exception: $exception,
            userIdentifier: 'user-xyz',
        );
    }

    /** @test */
    public function webhook_failed_does_not_leak_exception_message(): void
    {
        $logger = new ZoomWebhookLogger;

        // Create an exception with a fake credential in the message
        $exception = new RuntimeException('Authorization: Bearer FAKE_P19_REVIEW_SECRET');

        Log::shouldReceive('channel')->with('zoom_webhook_failed')->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->with(
                'zoom_webhook_failed',
                Mockery::on(function (array $context): bool {
                    // Verify the fake credential is NOT in the context
                    $contextJson = json_encode($context);

                    return ! str_contains($contextJson, 'FAKE_P19_REVIEW_SECRET')
                        && ! str_contains($contextJson, 'Authorization: Bearer')
                        // Verify safe identifiers ARE in the context
                        && isset($context['meeting_id'])
                        && $context['meeting_id'] === 67890
                        && isset($context['request_id'])
                        && $context['request_id'] === 'req-def-456'
                        && isset($context['user_id'])
                        && $context['user_id'] === 'user-uvw'
                        // Verify exception class and code are present
                        && isset($context['exception'])
                        && $context['exception'] === RuntimeException::class
                        && isset($context['exception_code'])
                        && $context['exception_code'] === 0
                        // Verify message field is absent
                        && ! array_key_exists('message', $context);
                })
            );

        $logger->logWebhookFailed(
            operation: 'meeting.updated',
            meetingId: 67890,
            requestId: 'req-def-456',
            exception: $exception,
            userIdentifier: 'user-uvw',
        );
    }

    /** @test */
    public function webhook_retry_scheduled_logs_exception_class_and_code(): void
    {
        $logger = new ZoomWebhookLogger;

        $exception = new RuntimeException('Some error message', 123);

        Log::shouldReceive('channel')->with('zoom')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'zoom_webhook_retry_scheduled',
                Mockery::on(fn (array $context): bool => isset($context['exception'])
                    && $context['exception'] === RuntimeException::class
                    && isset($context['exception_code'])
                    && $context['exception_code'] === 123
                    && ! array_key_exists('message', $context))
            );

        $logger->logWebhookRetryScheduled(
            operation: 'meeting.deleted',
            meetingId: 11111,
            requestId: 'req-111',
            exception: $exception,
        );
    }

    /** @test */
    public function webhook_failed_logs_exception_class_and_code(): void
    {
        $logger = new ZoomWebhookLogger;

        $exception = new RuntimeException('Some error message', 456);

        Log::shouldReceive('channel')->with('zoom_webhook_failed')->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->with(
                'zoom_webhook_failed',
                Mockery::on(fn (array $context): bool => isset($context['exception'])
                    && $context['exception'] === RuntimeException::class
                    && isset($context['exception_code'])
                    && $context['exception_code'] === 456
                    && ! array_key_exists('message', $context))
            );

        $logger->logWebhookFailed(
            operation: 'meeting.started',
            meetingId: 22222,
            requestId: 'req-222',
            exception: $exception,
        );
    }
}
