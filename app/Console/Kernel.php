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
            ->withoutOverlapping()
            ->runInBackground()
            ->everyMinute()
            ->when(fn (): bool => Message::messageScheduled()->exists());

        $schedule->command('tasks:notify')
            ->withoutOverlapping()
            ->runInBackground()
            ->everyTwoMinutes()
            ->when(fn (): bool => Task::dueForNotifications()
                ->count() > 0);

        $schedule->command('remove:abandon')->daily();
        $schedule->command('queue:prune-batches --hours=48 --unfinished=72')->daily();

        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run')->daily()->at('01:30');

        $schedule->command('telescope:prune --hours=10')->daily();
        $schedule->command('user:profile-delete')->daily();

        // Daily health score sweep (recalculate persisted health scores)
        $schedule->command('projects:recalculate-health --queue=metrics')->dailyAt('02:00')->name('recalculate-project-health');
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
