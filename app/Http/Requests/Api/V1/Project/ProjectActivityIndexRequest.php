<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class ProjectActivityIndexRequest extends ApiQueryRequest
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
             * Nested activity filters.
             *
             * @example {"type":"tasks"}
             */
            'filter' => ['sometimes', 'array:type'],
            /**
             * Filter activities by type. Allowed values: specifics, tasks, members, mine.
             *
             * @example tasks
             */
            'filter.type' => ['sometimes', 'in:specifics,tasks,members,mine'],
            ...$this->topLevelFilterAliasRules(['type']),
            ...$this->unsupportedQueryParameterRules(),
            /**
             * Page number for pagination.
             *
             * @example 1
             */
            'page' => $this->pageRule(),
            /**
             * Number of activities to return per page. Default is 10.
             *
             * @example 10
             */
            'per_page' => $this->perPageRule(),
        ];
    }

    public function filterType(): ?string
    {
        $type = $this->validated('filter.type');

        return is_string($type) ? $type : null;
    }

    public function perPage(): int
    {
        return $this->perPageValue(10);
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['type']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
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
        $this->mergeFilters($this->normalizedFilters());
    }
}
