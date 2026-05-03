<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

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
            'filter' => ['sometimes', 'array'],
            'filter.user_created' => ['sometimes', 'boolean'],
            'filter.task_assigned' => ['sometimes', 'boolean'],
            'filter.completed' => ['sometimes', 'boolean'],
            'filter.overdue' => ['sometimes', 'boolean'],
            'filter.remaining' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Convenience: return only known filter keys
     *
     * @return array{user_created: bool, task_assigned: bool, completed: bool, overdue: bool, remaining: bool}
     */
    public function filters(): array
    {
        $validatedFilters = $this->validated('filter', []);

        return [
            'user_created' => (bool) ($validatedFilters['user_created'] ?? false),
            'task_assigned' => (bool) ($validatedFilters['task_assigned'] ?? false),
            'completed' => (bool) ($validatedFilters['completed'] ?? false),
            'overdue' => (bool) ($validatedFilters['overdue'] ?? false),
            'remaining' => (bool) ($validatedFilters['remaining'] ?? false),
        ];
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
        if (! $this->hasAnyFilter($this->filters())) {
            throw ValidationException::withMessages([
                'filter' => 'At least one filter must be provided.',
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $filters
     */
    protected function hasAnyFilter(array $filters): bool
    {
        foreach ($filters as $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
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
