<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository
{
    private const array CONVERSATION_RESOURCE_RELATIONS = ['user', 'project:id,slug'];

    public function getProjectConversations(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        return $project->conversations()
            ->with(self::CONVERSATION_RESOURCE_RELATIONS)
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }
}
