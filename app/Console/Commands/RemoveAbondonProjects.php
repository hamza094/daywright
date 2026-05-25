<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class RemoveAbondonProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:abandon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove abandoned projects who has passed limit days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $projects = Project::onlyTrashed()
            ->pastAbandonedLimit()
            ->get();

        $projects->each(function ($project): void {
            $project->forceDelete();
        });

    }
}
