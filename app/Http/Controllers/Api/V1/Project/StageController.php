<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\StageResource;
use App\Models\Stage;

class StageController extends ApiController
{
    /**
     * Display a listing of the stages.
     *
     * Fetch and return all stages that a project can be assigned to.
     */
    public function index()
    {
        $stages = Stage::all();

        return StageResource::collection($stages);
    }
}
