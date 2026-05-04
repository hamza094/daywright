<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Api\V1\Dashboard\DashboardInsightsService;
use Illuminate\Http\JsonResponse;

final class DashboardKpisController extends ApiController
{
    public function __invoke(DashboardInsightsService $dashboardInsightsService): JsonResponse
    {
        $userId = $this->authenticatedUser()->id;

        return $this->respondWithData([
            'kpis' => $dashboardInsightsService->getKPIs($userId),
            'insights' => $dashboardInsightsService->getActionableInsights($userId),
        ]);
    }
}
