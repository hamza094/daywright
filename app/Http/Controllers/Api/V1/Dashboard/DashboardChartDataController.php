<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Dashboard\DashboardChartDataRequest;
use App\Repository\Dashboard\ProjectStatsRepository;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class DashboardChartDataController extends ApiController
{
    /**
     * Return project portfolio counts for the authenticated user's dashboard.
     *
     * Summarizes active, trashed, member, and total project counts for the selected dashboard time window.
     */
    #[Endpoint(operationId: 'dashboard.chartData')]
    #[ScrambleResponse(
        status: 200,
        description: 'Dashboard project counts grouped by active, trashed, and member access.',
        type: 'array{data: array{active_projects: int, trashed_projects: int, member_projects: int, total_projects: int}}',
    )]
    public function __invoke(DashboardChartDataRequest $request, ProjectStatsRepository $projectStatsRepository): JsonResponse
    {
        $dateFilter = $request->getDateFilter();

        $data = $projectStatsRepository->getProjectStats(
            $this->authenticatedUser()->id,
            $dateFilter->year,
            $dateFilter->month,
        );

        return $this->respondWithData($data);
    }
}
