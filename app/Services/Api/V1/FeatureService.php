<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Enums\StageStatus;
use App\Exports\ProjectsExport;
use Illuminate\Support\Facades\DB;

class FeatureService
{
    public function updateStageStatus($project, array $data)
    {
        DB::transaction(function () use ($project, $data): void {

            $project->stage()->associate($data['stage']);

            $project->update([
                'postponed_reason' => $this->getPostponedReason($project, $data),
                'stage_updated_at' => now(),
            ]);

        });

        return $project;
    }

    public function excelExport($project)
    {
        return (new ProjectsExport($project))->download("Project $project->name.xlsx");

        /*  return $this->respondWithSuccess([
         'message'=>$project->name . " file exported successfully",
   ]);*/

        // self::recordActivity($project,'export_project','default');
    }

    public function recordActivity($project, $activity, $info): void
    {
        $project->recordActivity($activity, $info);
    }

    private function getPostponedReason($project, array $data): ?string
    {
        return ($project->stage->name === StageStatus::Postponed->value && ! empty($data['postponed_reason']))
            ? $data['postponed_reason']
            : null;
    }
}
