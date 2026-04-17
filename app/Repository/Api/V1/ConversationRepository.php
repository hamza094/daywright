<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Models\Project;
use Illuminate\Pagination\CursorPaginator;

class ConversationRepository
{
    private const int PER_PAGE = 25;

    /**
     * Fetch project conversations with cursor-based pagination.
     *
     * @return CursorPaginator<\App\Models\Conversation>
     */
    public function getProjectConversations(Project $project, ?string $cursor = null): CursorPaginator
    {
        return $project->conversations()
            ->with(['user', 'project:id,slug'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor);
    }
}
