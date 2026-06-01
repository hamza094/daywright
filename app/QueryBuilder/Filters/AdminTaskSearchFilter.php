<?php

declare(strict_types=1);

namespace App\QueryBuilder\Filters;

use App\QueryBuilder\Concerns\EscapesLikeWildcards;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * @implements Filter<\App\Models\Task>
 */
final class AdminTaskSearchFilter implements Filter
{
    use EscapesLikeWildcards;

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query->whereHas('project', function (Builder $subQuery) use ($value): void {
            $this->likeContainsLiteral($subQuery, 'name', $value);
        });
    }
}
