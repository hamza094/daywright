<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\StageRequest;
use App\Http\Resources\Api\V1\Admin\StageResource;
use App\Models\Stage;
use App\Services\Admin\StageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StageController extends ApiController
{
    public function __construct(private readonly StageService $stageService) {}

    public function index(): AnonymousResourceCollection
    {
        $stages = $this->stageService->all();

        return StageResource::collection($stages);
    }

    public function store(StageRequest $request): JsonResponse
    {
        $stage = $this->stageService->create($request->validated());

        return $this->respondCreated(new StageResource($stage));
    }

    public function update(StageRequest $request, Stage $stage): JsonResponse
    {
        $stage = $this->stageService->update($stage, $request->validated());

        return $this->respondUpdated(new StageResource($stage));
    }

    public function destroy(Stage $stage): JsonResponse
    {
        $this->stageService->delete($stage);

        return $this->respondWithMessage('Stage deleted successfully');
    }
}
