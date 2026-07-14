<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\DataTransferObjects\Task\UserTaskFilters;
use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class UserTasksRequest extends ApiQueryRequest
{
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
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Nested dashboard task filters.
             */
            'filter' => ['sometimes', 'array:user_created,task_assigned,completed,overdue,remaining'],
            /**
             * Limit results to tasks created by the authenticated user.
             *
             * @example true
             */
            'filter.user_created' => ['sometimes', 'boolean'],
            /**
             * Limit results to tasks assigned to the authenticated user.
             *
             * @example true
             */
            'filter.task_assigned' => ['sometimes', 'boolean'],
            /**
             * Limit results to completed tasks.
             *
             * @example false
             */
            'filter.completed' => ['sometimes', 'boolean'],
            /**
             * Limit results to overdue tasks.
             *
             * @example true
             */
            'filter.overdue' => ['sometimes', 'boolean'],
            /**
             * Limit results to remaining, not-yet-completed tasks.
             *
             * @example false
             */
            'filter.remaining' => ['sometimes', 'boolean'],
            ...$this->topLevelFilterAliasRules(['user_created', 'task_assigned', 'completed', 'overdue', 'remaining']),
            ...$this->unsupportedQueryParameterRules(),
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['user_created', 'task_assigned', 'completed', 'overdue', 'remaining']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    public function filters(): UserTaskFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return UserTaskFilters::fromArray($validatedFilters);
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();
        $filters = $this->normalizeBooleanFilters($filters, ['user_created', 'task_assigned', 'completed', 'overdue', 'remaining']);

        $this->mergeFilters($filters);
    }
}
