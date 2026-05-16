<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\Task\UserTaskFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Override;

class UserTasksRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            /**
             * Nested dashboard task filters.
             */
            'filter' => ['sometimes', 'array'],
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
        ];
    }

    public function filters(): UserTaskFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return UserTaskFilters::fromArray($validatedFilters);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        foreach (['user_created', 'task_assigned', 'completed', 'overdue', 'remaining'] as $key) {
            if (! array_key_exists($key, $filters) && $this->has($key)) {
                $filters[$key] = $this->normalizeBooleanValue($this->input($key));
            } elseif (array_key_exists($key, $filters)) {
                $filters[$key] = $this->normalizeBooleanValue($filters[$key]);
            }
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }

    /**
     * Handle a passed validation attempt.
     */
    #[Override]
    protected function passedValidation(): void
    {
        if (! $this->filters()->hasAnyFilter()) {
            throw ValidationException::withMessages([
                'filter' => 'At least one filter must be provided.',
            ]);
        }
    }

    private function normalizeBooleanValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $value;
    }
}
