<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Override;

class TaskIndexRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
{
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
            /**
             * Nested task list filters.
             */
            'filter' => ['sometimes', 'array:state'],
            /**
             * Filter tasks by lifecycle state.
             * Use `filter[state]=archived` to retrieve archived tasks.
             *
             * @example archived
             */
            'filter.state' => ['sometimes', 'in:archived'],
            ...$this->topLevelFilterAliasRules(['state']),
            ...$this->unsupportedQueryParameterRules(),
            /**
             * Paginator page number for task results.
             *
             * @example 1
             */
            'page' => $this->pageRule(),
            /**
             * Number of tasks to return per page.
             *
             * @example 3
             */
            'per_page' => $this->perPageRule(),
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['state']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    public function isArchived(): bool
    {
        return $this->validated('filter.state') === 'archived';
    }

    public function perPage(): int
    {
        return $this->perPageValue((int) config('app.tasks.limit', 20));
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter', 'page', 'per_page'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();

        $this->mergeFilters($filters);
    }
}
