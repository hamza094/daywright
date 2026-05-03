<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class UserFilterRequest extends FormRequest
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
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function searchTerm(): ?string
    {
        $search = $this->validated('filter.search');

        return is_string($search) ? $search : null;
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 7);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (! array_key_exists('search', $filters) && $this->has('search')) {
            $filters['search'] = $this->input('search');
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
