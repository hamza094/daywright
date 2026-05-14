<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class TaskIndexRequest extends FormRequest
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
            'filter' => ['sometimes', 'array'],
            /**
             * Filter tasks by lifecycle state.
             * Use `archived` to retrieve archived tasks without pagination.
             *
             * @example archived
             */
            'filter.state' => ['sometimes', 'in:archived'],
            /**
             * Paginator page number for active task results.
             *
             * @example 1
             */
            'page' => ['sometimes', 'integer', 'min:1'],
            /**
             * Number of active tasks to return per page.
             *
             * @example 3
             */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function isArchived(): bool
    {
        return $this->validated('filter.state') === 'archived';
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', (int) config('tasks.limit', 3));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (! array_key_exists('state', $filters) && $this->input('request') === 'archived') {
            $filters['state'] = 'archived';
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
