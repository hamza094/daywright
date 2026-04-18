<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildCursorPaginatedPayloadAction;
use App\Actions\BuildPaginatedPayloadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DashboardProjectRequest;
use App\Http\Requests\Api\V1\UserActivitiesRequest;
use App\Http\Requests\Api\V1\UserTasksRequest;
use App\Http\Resources\Api\V1\ProjectsResource;
use App\Http\Resources\Api\V1\UserActivitiesResource;
use App\Http\Resources\Api\V1\UserTasksResource;
use App\Repository\DashBoardRepository;
use App\Repository\UserTasksDataRepository;
use App\Services\Api\V1\Dashboard\DashboardInsightsService;
use App\Services\Api\V1\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectDashboardController extends Controller
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
     * @response AnonymousResourceCollection<LengthAwarePaginator<ProjectsResource>>
     */
    public function userProjects(DashboardProjectRequest $request, BuildPaginatedPayloadAction $buildPaginatedPayloadAction): JsonResponse
    {
        $projectsPaginator = $this->dashboardService->getUserProjects($request);

        if (! $projectsPaginator) {
            return response()->json([
                'projects' => [],
                'projectsCount' => 0,
                'message' => 'Sorry No Projects Found',
            ]);
        }

        $projectsPayload = $buildPaginatedPayloadAction->handle($projectsPaginator, ProjectsResource::class);

        return response()->json([
            'projects' => $projectsPayload,
            'projectsCount' => $projectsPaginator->total(),
            'message' => $projectsPaginator->isEmpty() ? 'Sorry No Projects Found' : '',
        ]);
    }

    /**
     * Get recent active projects
     */
    public function dashboardProjects(): JsonResponse
    {
        $projects = $this->dashboardService->getDashboardProjects();

        $projectsResource = ProjectsResource::collection($projects);

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

    public function tasksData(
        UserTasksDataRepository $repository,
        UserTasksRequest $request,
        BuildCursorPaginatedPayloadAction $buildCursorPayload
    ): JsonResponse {
        $paginator = $repository->getTasks(auth()->id(), $request);
        $appliedFilters = $repository->appliedFilters($request);

        $payload = $buildCursorPayload->handle($paginator, UserTasksResource::class);
        $payload['meta']['applied_filters'] = $appliedFilters;

        return response()->json($payload);
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
