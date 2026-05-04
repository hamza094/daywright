<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Repository\DashBoardRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardChartDataController extends ApiController
{
    public function __invoke(Request $request, DashBoardRepository $dashboardRepository): JsonResponse
    {
        $data = $dashboardRepository->getProjectStats($request);

        return $this->respondWithData($data);
    }
}
