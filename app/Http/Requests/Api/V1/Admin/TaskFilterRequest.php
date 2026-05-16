<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Task\AdminTaskFilters;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class TaskFilterRequest extends FormRequest
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
            'filter' => ['sometimes', 'array'],
            'filter.state' => ['sometimes', 'in:active,trashed'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): AdminTaskFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return AdminTaskFilters::fromArray($validatedFilters);
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 50);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (is_string($inputFilters) && $inputFilters !== '') {
            $filters['state'] = $inputFilters;
        }

        if (! array_key_exists('search', $filters) && $this->has('search')) {
            $filters['search'] = $this->input('search');
        }

        if (! array_key_exists('state', $filters) && $this->has('state')) {
            $filters['state'] = $this->input('state');
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
