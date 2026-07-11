<?php

declare(strict_types=1);

namespace App\Repository\Dashboard;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            ->with([
                'subject',
                'project' => function (BelongsTo $query): void {
                    $query->withTrashed()
                        ->select([
                            'id',
                            'name',
                            'slug',
                            'stage_id',
                            'created_at',
                        ]);
                },
                'project.stage',
            ])
            ->orderBy('created_at')
            ->get();
    }
}
