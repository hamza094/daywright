<?php

declare(strict_types=1);

namespace App\QueryBuilder\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait EscapesLikeWildcards
{
    protected function escapeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Perform a LIKE search with escaped wildcards.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function likeContainsLiteral(Builder $query, string $column, string $value, string $boolean = 'and'): Builder
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        return $query->whereRaw(
            "{$wrappedColumn} LIKE ? ESCAPE '\\'",
            ['%'.$this->escapeLikeWildcards($value).'%'],
            $boolean,
        );
    }
}
