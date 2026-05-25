<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

final class MessageRepository
{
    private const array MESSAGE_RESOURCE_RELATIONS = ['users:id,uuid,name,username,avatar_path'];

    /**
     * @return LengthAwarePaginator<int, \App\Models\Message>
     */
    public function paginateScheduledMessages(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        return $project->messages()
            ->where('delivered', false)
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '>', now())
            ->with(self::MESSAGE_RESOURCE_RELATIONS)
            ->orderBy('delivered_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }
}
