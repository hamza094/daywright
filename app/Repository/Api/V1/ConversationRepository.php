<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ConversationRepository
{
    /**
     * @return Collection<int, \App\Models\Conversation>
     */
    public function getProjectConversations(Project $project): Collection
    {
        return $project->conversations()
            ->with(['user', 'project:id,slug'])
            ->orderBy('id')
            ->get();
    }
}
