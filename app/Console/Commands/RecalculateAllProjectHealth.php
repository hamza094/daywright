<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecalculateProjectHealth;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateAllProjectHealth extends Command
{
    protected $signature = 'projects:recalculate-health
                            {--queue=metrics : Queue name to dispatch jobs to}
                            {--chunk=200 : Chunk size for processing}
                            {--limit= : Optional limit of projects to process}';

    protected $description = 'Dispatch RecalculateProjectHealth jobs for all (or limited) projects';

    public function handle(): int
    {
        $queue = (string) $this->option('queue');
        $chunk = (int) $this->option('chunk');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Project::query()->select('id');
        if ($limit) {
            $query->limit($limit);
        }

        $this->info('Dispatching health recalculation jobs...');

        $dispatched = 0;

        $query->chunk($chunk, function ($projects) use ($queue, &$dispatched): void {
            foreach ($projects as $project) {
                try {
                    RecalculateProjectHealth::dispatch($project->id)->onQueue($queue);
                    $dispatched++;
                } catch (Throwable $e) {
                    Log::error('Failed to dispatch RecalculateProjectHealth for project '.$project->id, [
                        'exception_class' => $e::class,
                        'exception_code' => $e->getCode(),
                    ]);
                }
            }
        });

        $this->info("Health recalculation dispatch complete. Dispatched: {$dispatched}");

        return 0;
    }
}
