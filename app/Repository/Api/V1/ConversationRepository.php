<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Models\Project;
use Illuminate\Pagination\CursorPaginator;

class ConversationRepository
{
    private const array CONVERSATION_RESOURCE_RELATIONS = ['user', 'project:id,slug'];

    /**
     * @return CursorPaginator<int, \App\Models\Conversation>
     */
    public function getProjectConversations(Project $project, int $perPage): CursorPaginator
    {
        return $project->conversations()
            ->with(self::CONVERSATION_RESOURCE_RELATIONS)
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
