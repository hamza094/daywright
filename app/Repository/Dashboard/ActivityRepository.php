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
            ->with(['project' => function (\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation $query): void {
                $query->withTrashed() // @phpstan-ignore-line
                    ->select([
                        'id',
                        'name',
                        'slug',
                        'stage_id',
                        'created_at',
                    ]);
            }])
            ->orderBy('created_at')
            ->limit(100)
            ->get();
    }
}
