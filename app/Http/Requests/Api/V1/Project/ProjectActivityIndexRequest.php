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
            'filter' => ['sometimes', 'array:type'],
            'filter.type' => ['sometimes', 'in:specifics,tasks,members,mine'],
            ...$this->topLevelFilterAliasRules(['type']),
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
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

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->mergeFilters($this->normalizedFilters());
    }
}
