<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\StageResource;
use App\Services\Project\StageService;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StageController extends ApiController
{
    public function __construct(private readonly StageService $stageService) {}

    /**
     * Display a listing of the stages.
     *
     * Fetch and return all stages that a project can be assigned to.
     */
    #[Endpoint(operationId: 'stages.list')]
    public function index(): AnonymousResourceCollection
    {
        $stages = $this->stageService->all();

        return StageResource::collection($stages);
    }
}
