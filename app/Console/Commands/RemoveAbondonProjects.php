<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Project\ForceDeleteAbandonedProjectAction;
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
    public function handle(ForceDeleteAbandonedProjectAction $forceDeleteAbandonedProjectAction): void
    {
        Project::onlyTrashed()
            ->pastAbandonedLimit()
            ->chunkById(100, function ($projects) use ($forceDeleteAbandonedProjectAction): void {
                $projects->each(function (Project $project) use ($forceDeleteAbandonedProjectAction): void {
                    $forceDeleteAbandonedProjectAction->execute($project);
                });
            });
    }
}
