<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ForceDeleteProjectController extends ApiController
{
    /**
     * Permanently delete an abandoned project.
     *
     * This endpoint only applies to abandoned (soft-deleted) projects. It triggers a permanent cascade
     * delete of all associated resources including tasks, conversations, messages, meetings, and member associations.
     * This operation is irreversible.
     */
    #[Endpoint(operationId: 'projects.forceDelete')]
    public function __invoke(Project $project, ProjectService $projectService): JsonResponse
    {
        $deleted = $projectService->forceDeleteIfAbandoned($project);

        if (! $deleted) {
            throw new HttpException(HttpResponse::HTTP_FORBIDDEN, 'Only abandoned projects can be deleted permanently.');
        }

        return $this->respondWithMessage('Project deleted successfully.');
    }
}
