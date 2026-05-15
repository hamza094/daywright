<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\DashboardChartDataRequest;
use App\Repository\UserDashboardRepository;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

final class DashboardChartDataController extends ApiController
{
    /**
     * Return project portfolio counts for the authenticated user's dashboard.
     *
     * Summarizes active, trashed, member, and total project counts for the selected dashboard time window.
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Dashboard project counts grouped by active, trashed, and member access.',
        type: 'array{data: array{active_projects: int, trashed_projects: int, member_projects: int, total_projects: int}}',
    )]
    public function __invoke(DashboardChartDataRequest $request, UserDashboardRepository $dashboardRepository): JsonResponse
    {
        $validated = $request->validated();

        $data = $dashboardRepository->getProjectStats(
            $this->authenticatedUser()->id,
            isset($validated['year']) ? (int) $validated['year'] : null,
            isset($validated['month']) ? (int) $validated['month'] : null,
        );

        return $this->respondWithData($data);
    }
}
