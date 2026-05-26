<?php

declare(strict_types=1);

namespace App\Actions\ProjectMetrics;

use App\Enums\ProjectStage;
use App\Models\Project;

final class StageProgressMetricAction
{
    /**
     * @return array{
     *   percentage: float|int,
     *   current_stage: string,
     *   status: string,
     *   stage_id: int|null
     * }
     */
    public function execute(Project $project): array
    {
        $stageEnum = ProjectStage::tryFrom($project->stage_id);

        return [
            'percentage' => $stageEnum?->progress() ?? 0,
            'current_stage' => $stageEnum?->label() ?? 'Unknown',
            'status' => $stageEnum?->status() ?? 'unknown',
            'stage_id' => $project->stage_id,
        ];
    }
}
