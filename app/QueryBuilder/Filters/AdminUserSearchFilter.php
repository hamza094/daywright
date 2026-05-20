<?php

declare(strict_types=1);

namespace App\QueryBuilder\Filters;

use App\QueryBuilder\Concerns\EscapesLikeWildcards;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

final class AdminUserSearchFilter implements Filter
{
    use EscapesLikeWildcards;

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($value): void {
            $this->likeContainsLiteral($searchQuery, 'name', $value);
            $this->likeContainsLiteral($searchQuery, 'username', $value, 'or');
            $this->likeContainsLiteral($searchQuery, 'email', $value, 'or');
        });
    }
}
