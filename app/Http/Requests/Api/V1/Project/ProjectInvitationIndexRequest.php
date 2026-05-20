<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Override;

class ProjectInvitationIndexRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
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
            'filter' => ['required', 'array:status'],
            'filter.status' => ['required', 'in:pending'],
            ...$this->unsupportedQueryParameterRules(),
        ];
    }

    public function status(): string
    {
        return (string) $this->validated('filter.status');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'filter.required' => 'Please provide an invitation status filter.',
            'filter.array' => 'Only the supported filter keys may be provided.',
            'filter.status.required' => 'Please provide an invitation status filter.',
            'filter.status.in' => 'The invitation status filter must be pending.',
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();

        $this->mergeFilters($filters);
    }
}
