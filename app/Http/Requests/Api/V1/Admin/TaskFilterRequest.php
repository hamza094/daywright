<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use App\QueryBuilder\Filters\AdminTaskSearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class TaskFilterRequest extends ApiQueryRequest
{
    /**
     * @return array<int, AllowedFilter|string>
     */
    #[Override]
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('search', new AdminTaskSearchFilter),
            AllowedFilter::callback('state', static function (Builder $query, mixed $value): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                match ($value) {
                    'active' => $query->whereNull('deleted_at'),
                    'trashed' => $query->whereNotNull('deleted_at'),
                    default => null,
                };
            }),
        ];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    #[Override]
    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('created_at'),
            AllowedSort::field('title'),
            AllowedSort::field('due_at'),
        ];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    #[Override]
    public static function defaultSorts(): array
    {
        return ['-created_at'];
    }

    /**
     * @return array<int, AllowedInclude|string>
     */
    #[Override]
    public static function allowedIncludes(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,state'],
            'filter.state' => ['sometimes', 'in:active,trashed'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            ...$this->topLevelFilterAliasRules(['search', 'state']),
            'sort' => ['sometimes', 'string', 'in:-created_at,created_at,title,-title,due_at,-due_at'],
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
            'per_page' => $this->perPageRule(),
        ];
    }

    public function perPage(): int
    {
        return $this->perPageValue(50);
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['search', 'state']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter', 'sort', 'page', 'per_page'];
    }
}
