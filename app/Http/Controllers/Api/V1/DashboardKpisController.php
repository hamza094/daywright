<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Dashboard\DashboardInsightsService;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class DashboardKpisController extends ApiController
{
    /**
     * Return KPI cards and actionable insights for the authenticated user's dashboard.
     *
     * Combines portfolio metrics and generated insight cards into a single dashboard response.
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Dashboard KPIs and prioritized insights generated from the current project portfolio.',
        type: 'array{data: array{kpis: array{total_projects: array{value: int, label: string, status: string}, critical_projects: array{value: int, label: string, status: string}, overdue_tasks: array{value: int, label: string, status: string}, completion_rate: array{value: float|int, label: string, status: string}}, insights: array<int, array{type: string, title: string, message: string, action: string, priority: string}>}}',
    )]
    public function __invoke(DashboardInsightsService $dashboardInsightsService): JsonResponse
    {
        $userId = $this->authenticatedUser()->id;

        return $this->respondWithData([
            'kpis' => $dashboardInsightsService->getKPIs($userId),
            'insights' => $dashboardInsightsService->getActionableInsights($userId),
        ]);
    }
}
