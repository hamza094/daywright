<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Enums\ProjectHealthStatus;
use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class ProjectFilterRequest extends ApiQueryRequest
{
    // Static allowlist hooks removed — this request relies on `rules()` for
    // validation. If Spatie QueryBuilder is adopted for this endpoint in the
    // future, reintroduce `allowedFilters()`, `allowedSorts()`, and
    // `defaultSorts()` here as the single source of truth for allowlists.

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:state,search,members,status,tasks,stage,from,to'],
            'filter.state' => ['sometimes', 'in:active,trashed'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'filter.members' => ['sometimes', 'nullable', 'boolean'],
            'filter.status' => ['sometimes', 'required', 'in:'.implode(',', array_column(ProjectHealthStatus::cases(), 'value'))],
            'filter.tasks' => ['sometimes', 'nullable', 'boolean'],
            'filter.stage' => ['sometimes', 'required', 'integer', 'min:0', 'max:6'],
            'filter.from' => ['sometimes', 'required', 'date', 'required_with:filter.to'],
            'filter.to' => ['sometimes', 'required', 'date', 'required_with:filter.from'],
            ...$this->topLevelFilterAliasRules(['state', 'search', 'members', 'status', 'tasks', 'stage', 'from', 'to']),
            'sort' => ['sometimes', 'required', 'in:-created_at,created_at,name,-name,health_score,-health_score'],
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
            'per_page' => $this->perPageRule(),
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['state', 'search', 'members', 'status', 'tasks', 'stage', 'from', 'to']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    public function filters(): AdminProjectFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return AdminProjectFilters::fromArray([
            ...$validatedFilters,
            'sort' => $this->validated('sort'),
        ]);
    }

    public function perPage(): int
    {
        return $this->perPageValue(10);
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
        $filters = $this->normalizeBooleanFilters($filters, ['members', 'tasks']);
        $filters = $this->lowercaseFilterValue($filters, 'status');

        // No client-side alias or casing normalization for `sort` here.
        // The API requires canonical, lowercase snake_case tokens
        // (for example `created_at` or `-created_at`) and validation
        // enforces the allowed values.

        $this->mergeFilters($filters);
    }
}
