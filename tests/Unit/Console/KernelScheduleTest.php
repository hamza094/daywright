<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KernelScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function schedule_message_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $scheduleMessage = collect($events)->first(fn ($event) => str_contains($event->command, 'schedule:message'));

        $this->assertNotNull($scheduleMessage, 'schedule:message command not found in schedule');
        $this->assertTrue($scheduleMessage->withoutOverlapping);
        $this->assertTrue($scheduleMessage->onOneServer);
        $this->assertNotNull($scheduleMessage->output);
    }

    /** @test */
    public function tasks_notify_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $tasksNotify = collect($events)->first(fn ($event) => str_contains($event->command, 'tasks:notify'));

        $this->assertNotNull($tasksNotify, 'tasks:notify command not found in schedule');
        $this->assertTrue($tasksNotify->withoutOverlapping);
        $this->assertTrue($tasksNotify->onOneServer);
        $this->assertNotNull($tasksNotify->output);
    }

    /** @test */
    public function remove_abandon_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $removeAbandon = collect($events)->first(fn ($event) => str_contains($event->command, 'remove:abandon'));

        $this->assertNotNull($removeAbandon, 'remove:abandon command not found in schedule');
        $this->assertTrue($removeAbandon->withoutOverlapping);
        $this->assertTrue($removeAbandon->onOneServer);
    }

    /** @test */
    public function queue_prune_batches_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $queuePruneBatches = collect($events)->first(fn ($event) => str_contains($event->command, 'queue:prune-batches'));

        $this->assertNotNull($queuePruneBatches, 'queue:prune-batches command not found in schedule');
        $this->assertTrue($queuePruneBatches->withoutOverlapping);
        $this->assertTrue($queuePruneBatches->onOneServer);
    }

    /** @test */
    public function queue_prune_failed_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $queuePruneFailed = collect($events)->first(fn ($event) => str_contains($event->command, 'queue:prune-failed'));

        $this->assertNotNull($queuePruneFailed, 'queue:prune-failed command not found in schedule');
        $this->assertTrue($queuePruneFailed->withoutOverlapping);
        $this->assertTrue($queuePruneFailed->onOneServer);
        $this->assertStringContainsString('--hours=720', $queuePruneFailed->command);
    }

    /** @test */
    public function backup_clean_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $backupClean = collect($events)->first(fn ($event) => str_contains($event->command, 'backup:clean'));

        $this->assertNotNull($backupClean, 'backup:clean command not found in schedule');
        $this->assertTrue($backupClean->withoutOverlapping);
        $this->assertTrue($backupClean->onOneServer);
    }

    /** @test */
    public function backup_run_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $backupRun = collect($events)->first(fn ($event) => str_contains($event->command, 'backup:run'));

        $this->assertNotNull($backupRun, 'backup:run command not found in schedule');
        $this->assertTrue($backupRun->withoutOverlapping);
        $this->assertTrue($backupRun->onOneServer);
    }

    /** @test */
    public function telescope_prune_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $telescopePrune = collect($events)->first(fn ($event) => str_contains($event->command, 'telescope:prune'));

        $this->assertNotNull($telescopePrune, 'telescope:prune command not found in schedule');
        $this->assertTrue($telescopePrune->withoutOverlapping);
        $this->assertTrue($telescopePrune->onOneServer);
    }

    /** @test */
    public function user_profile_delete_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $userProfileDelete = collect($events)->first(fn ($event) => str_contains($event->command, 'user:profile-delete'));

        $this->assertNotNull($userProfileDelete, 'user:profile-delete command not found in schedule');
        $this->assertTrue($userProfileDelete->withoutOverlapping);
        $this->assertTrue($userProfileDelete->onOneServer);
    }

    /** @test */
    public function projects_recalculate_health_command_has_proper_configuration(): void
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $recalculateHealth = collect($events)->first(fn ($event) => str_contains($event->command, 'projects:recalculate-health'));

        $this->assertNotNull($recalculateHealth, 'projects:recalculate-health command not found in schedule');
        $this->assertTrue($recalculateHealth->withoutOverlapping);
        $this->assertTrue($recalculateHealth->onOneServer);
    }
}
