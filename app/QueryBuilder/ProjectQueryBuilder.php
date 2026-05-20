<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\QueryBuilder\Concerns\EscapesLikeWildcards;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\App\Models\Project>
 *
 * @method $this onlyTrashed()
 */
class ProjectQueryBuilder extends Builder
{
    use EscapesLikeWildcards;

    /**
     * Search projects by name
     */
    public function search(string $search): self
    {
        return $this->likeContainsLiteral($this, 'name', $search);
    }

    /**
     * Sort projects by different criteria
     */
    public function sortBy(string $sortBy = '-created_at'): self
    {
        return match ($sortBy) {
            'created_at' => $this->orderBy('created_at', 'asc'),
            '-created_at' => $this->orderBy('created_at', 'desc'),
            'name' => $this->orderBy('name', 'asc'),
            '-name' => $this->orderBy('name', 'desc'),
            default => $this->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Filter trashed projects
     */
    public function trashed(): self
    {
        return $this->onlyTrashed();
    }

    /**
     * Filter projects past abandoned limit
     */
    public function pastAbandonedLimit(): self
    {
        $abandonedLimit = config('app.project.abandonedLimit');

        return $this->where('deleted_at', '<', Carbon::now()->subDays($abandonedLimit));
    }
}
