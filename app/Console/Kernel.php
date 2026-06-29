<?php

declare(strict_types=1);

namespace App\Console;

use App\Models\Message;
use App\Models\Task;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Override;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\UserProfileDelete::class,
        Commands\RecalculateAllProjectHealth::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    #[Override]
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('schedule:message')
            ->name('schedule-message')
            ->onOneServer()
            ->withoutOverlapping()
            ->everyMinute()
            ->appendOutputTo(storage_path('logs/scheduler.log'))
            ->when(fn (): bool => Message::messageScheduled()->exists());

        $schedule->command('tasks:notify')
            ->name('tasks-notify')
            ->onOneServer()
            ->withoutOverlapping()
            ->everyTwoMinutes()
            ->appendOutputTo(storage_path('logs/scheduler.log'))
            ->when(fn (): bool => Task::dueForNotifications()
                ->count() > 0);

        $schedule->command('remove:abandon')
            ->name('remove-abandon')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily();
        $schedule->command('queue:prune-batches --hours=48 --unfinished=72')
            ->name('queue-prune-batches')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily();
        $schedule->command('queue:prune-failed --hours=720')
            ->name('queue-prune-failed')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily();

        $schedule->command('backup:clean')
            ->name('backup-clean')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily()
            ->at('01:00');
        $schedule->command('backup:run')
            ->name('backup-run')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily()
            ->at('01:30');

        $schedule->command('telescope:prune --hours=10')
            ->name('telescope-prune')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily();
        $schedule->command('user:profile-delete')
            ->name('user-profile-delete')
            ->onOneServer()
            ->withoutOverlapping()
            ->daily();

        // Daily health score sweep (recalculate persisted health scores)
        $schedule->command('projects:recalculate-health --queue=metrics')
            ->name('recalculate-project-health')
            ->onOneServer()
            ->withoutOverlapping()
            ->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    #[Override]
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    #[Override]
    protected function bootstrappers()
    {
        return array_merge(
            [\Bugsnag\BugsnagLaravel\OomBootstrapper::class],
            parent::bootstrappers(),
        );
    }
}
