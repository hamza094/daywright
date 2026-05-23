<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\DashboardProjectFilters;
use Override;

class DashboardProjectRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
{
    // `allowedFilters()` and `allowedSorts()` removed — validation is enforced
    // via `rules()`. Reintroduce these only if this request is wired into
    // Spatie\QueryBuilder in the future.

    /**
     * @return array<int, string>
     */
    public static function defaultSorts(): array
    {
        return ['-created_at'];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,member,abandoned'],
            'filter.search' => ['nullable', 'string', 'max:25'],
            ...$this->topLevelFilterAliasRules(['search', 'member', 'abandoned']),
            'sort' => ['nullable', 'string', 'in:-created_at,created_at,name,-name'],
            'filter.member' => ['nullable', 'boolean'],
            'filter.abandoned' => ['nullable', 'boolean'],
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule('nullable'),
            'per_page' => $this->perPageRule('nullable'),
        ];
    }

    public function filters(): DashboardProjectFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return DashboardProjectFilters::fromArray($validatedFilters);
    }

    public function sort(): string
    {
        return $this->sortValue(static::defaultSorts()[0]);
    }

    public function perPage(): int
    {
        return $this->perPageValue((int) config('app.project.items_limit'));
    }

    /**
     * Get custom messages for validator errors.
     */
    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            'sort.in' => 'Sort must be one of: -created_at, created_at, name, or -name',
            'page.min' => 'Page must be at least 1',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page may not be greater than 100',
            ...$this->topLevelFilterAliasMessages(['search', 'member', 'abandoned']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter', 'sort', 'page', 'per_page'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();
        $filters = $this->normalizeBooleanFilters($filters, ['member', 'abandoned']);

        // No alias or casing normalization for `sort` here — requests must
        // provide canonical lowercase snake_case tokens (e.g. `created_at` or
        // `-created_at`) and validation will enforce allowed values.

        $this->mergeFilters($filters);
    }
}
