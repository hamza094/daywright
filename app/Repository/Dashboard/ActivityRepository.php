<?php

declare(strict_types=1);

namespace App\Repository\Dashboard;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ActivityRepository
{
    /**
     * @return EloquentCollection<int, Activity>
     */
    public function getUserActivities(int $userId, Carbon $startDate, Carbon $endDate): EloquentCollection
    {
        return Activity::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['subject', 'project.stage'])
            ->with(['project' => function (mixed $query): void {
                $query->withTrashed()
                    ->select([
                        'id',
                        'name',
                        'slug',
                        'stage_id',
                        'created_at',
                    ]);
            }])
            ->orderBy('created_at')
            ->get();
    }
}
