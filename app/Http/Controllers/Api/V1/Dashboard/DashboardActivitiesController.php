<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\User\UserActivitiesRequest;
use App\Http\Resources\Api\V1\User\UserActivitiesResource;
use App\Repository\Dashboard\ActivityRepository;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DashboardActivitiesController extends ApiController
{
    /**
     * Return the authenticated user's dashboard activity feed for a date range.
     *
     * Lists dashboard activity entries between the requested start and end dates.
     * Uses top-level `start_date` and `end_date` parameters for date filtering.
     */
    #[Endpoint(operationId: 'dashboard.activities')]
    public function __invoke(UserActivitiesRequest $request, ActivityRepository $activityRepository): AnonymousResourceCollection
    {
        $dateRange = $request->getDateRange();

        $activities = $activityRepository->getUserActivities(
            $this->authenticatedUser()->id,
            $dateRange->startDate,
            $dateRange->endDate
        );

        return UserActivitiesResource::collection($activities);
    }
}
