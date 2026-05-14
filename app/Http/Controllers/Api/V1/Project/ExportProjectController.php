<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Exports\ProjectsExport;
use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExportProjectController extends ApiController
{
    public function __invoke(Project $project): BinaryFileResponse
    {
        return Excel::download(new ProjectsExport($project), "Project $project->name.xls");
    }
}
