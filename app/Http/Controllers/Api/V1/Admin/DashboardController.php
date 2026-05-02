<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\ActivitiesResource;
use App\Models\Activity;
use App\Services\Api\V1\Admin\DashboardService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    #[ExcludeRouteFromDocs]
    public function backup(): JsonResponse
    {
        try {
            Artisan::call('backup:clean');
            Artisan::call('backup:run');

            return response()->json(['message' => 'Backup process started']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function activities(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $activities = Activity::with('user', 'subject', 'project')->latest()->limit(15)->get();

            return ActivitiesResource::collection($activities);
        } catch (Throwable $e) {
            Log::error('Failed to load admin activities', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to load activities.'], 500);
        }
    }

    #[ExcludeRouteFromDocs]
    public function data(Request $request): JsonResponse
    {
        try {
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $data = $this->dashboardService->fetchDataForMonths($startDate, $endDate);

            return response()->json($data);
        } catch (Throwable $e) {
            Log::error('Failed to load admin dashboard data', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to load dashboard data.'], 500);
        }
    }
}
