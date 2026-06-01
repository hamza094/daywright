<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\PurgeDeletedUsersAction;
use Illuminate\Console\Command;

class UserProfileDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:profile-delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete user profiles after 15 days of soft deletion, and handle related projects.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        (new PurgeDeletedUsersAction)->execute();
        $this->info('User profile deletion process completed.');

        return 0;
    }
}
