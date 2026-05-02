<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StageRequest;
use App\Http\Resources\Api\V1\Admin\StageResource;
use App\Models\Stage;
use F9Web\ApiResponseHelpers;
use Illuminate\Http\JsonResponse;

class StageController extends Controller
{
    use ApiResponseHelpers;

    public function index()
    {
        $stages = Stage::all();

        return StageResource::collection($stages);
    }

    public function store(StageRequest $request): JsonResponse
    {
        $stage = Stage::create($request->validated());

        return $this->respondCreated([
            'message' => 'Stage created successfully',
            'stage' => new StageResource($stage),
        ]);
    }

    public function update(StageRequest $request, Stage $stage): JsonResponse
    {
        $stage->update($request->validated());

        return $this->respondWithSuccess([
            'message' => 'Stage updated successfully',
            'stage' => new StageResource($stage),
        ]);
    }

    public function destroy(Stage $stage): JsonResponse
    {
        $stage->delete();

        return response()->json([
            'message' => 'Stage deleted successfully',
        ], 200);
    }
}
