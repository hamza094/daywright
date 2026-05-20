<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\Admin\ActivitiesResource;
use App\Services\Admin\DashboardService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardController extends ApiController
{
    public function __construct(protected DashboardService $dashboardService) {}

    #[ExcludeRouteFromDocs]
    public function backup(): JsonResponse
    {
        try {
            Artisan::call('backup:clean');
            Artisan::call('backup:run');

            return $this->respondWithMessage('Backup process started');
        } catch (Throwable $e) {
            throw new ExternalServiceUnavailableException('Backup process could not be started.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }

    public function activities(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $activities = $this->dashboardService->recentActivities();

            return ActivitiesResource::collection($activities);
        } catch (Throwable $e) {
            Log::error('Failed to load admin activities', ['error' => $e->getMessage()]);

            throw new ExternalServiceUnavailableException('Failed to load activities.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }

    #[ExcludeRouteFromDocs]
    public function data(): JsonResponse
    {
        try {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $data = $this->dashboardService->fetchDataForMonths($startDate, $endDate);

            return $this->respondWithData($data);
        } catch (Throwable $e) {
            Log::error('Failed to load admin dashboard data', ['error' => $e->getMessage()]);

            throw new ExternalServiceUnavailableException('Failed to load dashboard data.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }
}
