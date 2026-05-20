<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Override;

class ProjectActivityIndexRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
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
            'tasks' => ['prohibited'],
            'members' => ['prohibited'],
            'mine' => ['prohibited'],
            'specifics' => ['prohibited'],
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
            'tasks.prohibited' => 'Use filter[type]=tasks instead of the top-level tasks parameter.',
            'members.prohibited' => 'Use filter[type]=members instead of the top-level members parameter.',
            'mine.prohibited' => 'Use filter[type]=mine instead of the top-level mine parameter.',
            'specifics.prohibited' => 'Use filter[type]=specifics instead of the top-level specifics parameter.',
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->mergeFilters($this->normalizedFilters());
    }
}
