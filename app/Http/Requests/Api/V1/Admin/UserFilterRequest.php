<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use App\QueryBuilder\Filters\AdminUserSearchFilter;
use Override;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

class UserFilterRequest extends ApiQueryRequest
{
    /**
     * @return array<int, AllowedFilter|string>
     */
    #[Override]
    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('search', new AdminUserSearchFilter),
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
            AllowedSort::field('name'),
            AllowedSort::field('email'),
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
            'filter' => ['sometimes', 'array:search'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            ...$this->topLevelFilterAliasRules(['search']),
            'sort' => ['sometimes', 'string', 'in:-created_at,created_at,name,-name,email,-email'],
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
            'per_page' => $this->perPageRule(),
        ];
    }

    public function perPage(): int
    {
        return $this->perPageValue(7);
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['search']),
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

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();

        // Strict canonical sort tokens required; validation enforces allowed sorts.

        $this->mergeFilters($filters);
    }
}
