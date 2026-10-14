<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Meetings;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Asserts that the provider-backed recovery commands that make sequential
 * external HTTP calls (each up to 30 s) are scheduled with runInBackground()
 * so they cannot stall the main Laravel scheduler process.
 *
 * Without runInBackground() a single slow Zoom/Vonage response would delay
 * every subsequent cron task for the duration of that external call.
 */
final class SchedulerRecoveryConfigTest extends TestCase
{
    #[Test]
    public function recover_ambiguous_zoom_meetings_runs_in_background(): void
    {
        $events = $this->indexedSchedule();

        $this->assertArrayHasKey(
            'recover-ambiguous-zoom-meetings',
            $events,
            'Expected a scheduled event named "recover-ambiguous-zoom-meetings".',
        );

        $event = $events['recover-ambiguous-zoom-meetings'];

        $this->assertTrue(
            $event->runInBackground,
            'meetings:recover-ambiguous must run in background to prevent blocking the scheduler process '
            .'during slow Zoom API calls (up to 30 s per request × 25 iterations).',
        );
    }

    #[Test]
    public function recover_ambiguous_zoom_meetings_has_overlapping_mutex(): void
    {
        $events = $this->indexedSchedule();

        $event = $events['recover-ambiguous-zoom-meetings'];

        $this->assertTrue(
            $event->withoutOverlapping,
            'meetings:recover-ambiguous must use withoutOverlapping() so concurrent runs are prevented.',
        );
        $this->assertSame(
            15,
            $event->expiresAt,
            'meetings:recover-ambiguous must specify a 15-minute expiration to cover worst-case execution time.',
        );
    }

    #[Test]
    public function recover_pending_zoom_operations_runs_in_background(): void
    {
        $events = $this->indexedSchedule();

        $this->assertArrayHasKey('recover-pending-zoom-operations', $events);

        $this->assertTrue(
            $events['recover-pending-zoom-operations']->runInBackground,
            'meetings:recover-pending must run in background.',
        );
    }

    #[Test]
    public function recover_subscription_operations_runs_in_background(): void
    {
        $events = $this->indexedSchedule();

        $this->assertArrayHasKey('recover-subscription-operations', $events);

        $this->assertTrue(
            $events['recover-subscription-operations']->runInBackground,
            'subscriptions:recover-operations must run in background.',
        );
    }

    /** @return array<string, Event> keyed by event name */
    private function indexedSchedule(): array
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $index = [];
        foreach ($schedule->events() as $event) {
            if ($event->description) {
                $index[$event->description] = $event;
            }
        }

        return $index;
    }
}
