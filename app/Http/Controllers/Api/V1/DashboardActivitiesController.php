<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UserActivitiesRequest;
use App\Http\Resources\Api\V1\User\UserActivitiesResource;
use App\Repository\DashBoardRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DashboardActivitiesController extends ApiController
{
    /**
     * Return the authenticated user's dashboard activity feed for a date range.
     *
     * Lists dashboard activity entries between the requested start and end dates.
     */
    public function __invoke(UserActivitiesRequest $request, DashBoardRepository $repository): AnonymousResourceCollection
    {
        $dateRange = $request->getDateRange();

        $activities = $repository->getUserActivities(
            $this->authenticatedUser()->id,
            $dateRange['start_date'],
            $dateRange['end_date']
        );

        return UserActivitiesResource::collection($activities);
    }
}
