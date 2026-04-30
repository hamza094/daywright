<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\DashboardProjectRequest;
use App\Http\Requests\Api\V1\UserActivitiesRequest;
use App\Http\Requests\Api\V1\UserTasksRequest;
use App\Http\Resources\Api\V1\Project\ProjectCollectionResource;
use App\Http\Resources\Api\V1\User\UserActivitiesResource;
use App\Http\Resources\Api\V1\User\UserTasksResource;
use App\Repository\DashBoardRepository;
use App\Repository\UserTasksDataRepository;
use App\Services\Api\V1\Dashboard\DashboardInsightsService;
use App\Services\Api\V1\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectDashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly DashboardInsightsService $dashboardInsightsService
    ) {}

    /**
     * Get paginated list of user's projects with filters
     *
     * List and filter user's projects. Supports pagination with defined items per page.
     *
     * @response AnonymousResourceCollection<LengthAwarePaginator<ProjectCollectionResource>>
     */
    public function userProjects(DashboardProjectRequest $request): JsonResponse
    {
        $projects = $this->dashboardService->getUserProjects($request);

        return response()->json([
            'projects' => ProjectCollectionResource::collection($projects)->paginate(config('app.project.items_limit')),
            'projectsCount' => $projects->count(),
            'message' => $projects->isEmpty() ? 'No projects found.' : '',
        ]);
    }

    /**
     * Get recent active projects
     */
    public function dashboardProjects(): JsonResponse
    {
        $projects = $this->dashboardService->getDashboardProjects();

        $projectsResource = ProjectCollectionResource::collection($projects);

        return response()->json([
            'projects' => $projectsResource,
            'projectsCount' => $projects->count(),
            'message' => $projects->isEmpty() ? 'No active projects found' : '',
        ]);
    }

    /**
     * Get user project activities
     */
    public function activities(UserActivitiesRequest $request, DashBoardRepository $repository): AnonymousResourceCollection
    {
        $dateRange = $request->getDateRange();

        $activities = $repository->getUserActivities(
            auth()->id(),
            $dateRange['start_date'],
            $dateRange['end_date']
        );

        return UserActivitiesResource::collection($activities);
    }

    public function chartData(Request $request, DashBoardRepository $dashboardRepository): JsonResponse
    {
        $data = $dashboardRepository->getProjectStats($request);

        return response()->json([
            'success' => true,
            'message' => 'Project stats fetched successfully.',
            'data' => $data,
        ]);
    }

    public function tasksData(UserTasksDataRepository $repository, UserTasksRequest $request): JsonResponse
    {
        $tasks = $repository->getTasks(auth()->id(), $request);
        $appliedFilters = $repository->appliedFilters($request);

        return response()->json([
            'data' => UserTasksResource::collection($tasks),
            'meta' => [
                'applied_filters' => $appliedFilters,
                'total' => $tasks->count(),
            ],
        ]);
    }

    /**
     * Get dashboard KPIs (and actionable insights)
     */
    public function kpis(): JsonResponse
    {
        $userId = auth()->id();
        $kpis = $this->dashboardInsightsService->getKPIs($userId);
        $insights = $this->dashboardInsightsService->getActionableInsights($userId);

        return response()->json([
            'kpis' => $kpis,
            'insights' => $insights,
        ]);
    }
}
