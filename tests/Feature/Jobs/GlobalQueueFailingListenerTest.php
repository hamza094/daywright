<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GlobalQueueFailingListenerTest extends TestCase
{
    /** @test */
    public function global_listener_logs_critical_queue_failures(): void
    {
        $exception = new RuntimeException('Test exception');

        $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
        $job->shouldReceive('getQueue')->andReturn('critical');
        $job->shouldReceive('uuid')->andReturn('test-uuid');
        $job->shouldReceive('attempts')->andReturn(3);
        $job->shouldReceive('payload')->andReturn(['tags' => ['tag1', 'tag2']]);

        $event = new JobFailed('database', $job, $exception);

        Log::shouldReceive('channel')
            ->once()
            ->with('queue_critical')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Critical queue job failed permanently',
                Mockery::on(fn (array $context): bool => $context['queue'] === 'critical' &&
                    $context['uuid'] === 'test-uuid' &&
                    $context['attempts'] === 3 &&
                    isset($context['exception']) &&
                    $context['exception'] instanceof RuntimeException &&
                    $context['tags'] === ['tag1', 'tag2'])
            );

        // Dispatch the event that AppServiceProvider listens to
        event($event);

        $this->assertTrue(true);
    }
}
