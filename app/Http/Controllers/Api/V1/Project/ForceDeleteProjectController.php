<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ForceDeleteProjectController extends ApiController
{
    public function __invoke(Project $project, ProjectService $projectService): JsonResponse
    {
        $deleted = $projectService->forceDeleteIfAbandoned($project);

        if (! $deleted) {
            throw new HttpException(HttpResponse::HTTP_FORBIDDEN, 'Only abandoned projects can be deleted permanently.');
        }

        return $this->respondWithMessage('Project deleted successfully');
    }
}
