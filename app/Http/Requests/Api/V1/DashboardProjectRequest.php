<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class DashboardProjectRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            /**
             * @example frontend
             */
            'filter.search' => ['nullable', 'string', 'max:25'],
            /**
             * @example latest
             */
            'sort' => ['nullable', 'string', 'in:latest,oldest,name'],
            /**
             * @example true
             */
            'filter.member' => ['nullable', 'boolean'],
            /**
             * @example false
             */
            'filter.abandoned' => ['nullable', 'boolean'],
            /**
             * @example 1
             */
            'page' => ['nullable', 'integer', 'min:1'],
            /**
             * @example 10
             */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{search: ?string, member: bool, abandoned: bool}
     */
    public function filters(): array
    {
        $validatedFilters = $this->validated('filter', []);

        return [
            'search' => is_string($validatedFilters['search'] ?? null) ? $validatedFilters['search'] : null,
            'member' => (bool) ($validatedFilters['member'] ?? false),
            'abandoned' => (bool) ($validatedFilters['abandoned'] ?? false),
        ];
    }

    public function sort(): string
    {
        return (string) $this->validated('sort', 'latest');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', (int) config('app.project.items_limit'));
    }

    /**
     * Get custom messages for validator errors.
     */
    #[Override]
    public function messages(): array
    {
        return [
            'sort.in' => 'Sort must be one of: latest, oldest, or name',
            'page.min' => 'Page must be at least 1',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page may not be greater than 100',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->input('filter', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        if (! array_key_exists('search', $filters) && $this->has('search')) {
            $filters['search'] = $this->input('search');
        }

        $member = $filters['member'] ?? $this->input('member');
        $abandoned = $filters['abandoned'] ?? $this->input('abandoned');

        $this->merge([
            'filter' => array_merge($filters, [
                'member' => $this->normalizeBooleanValue($member),
                'abandoned' => $this->normalizeBooleanValue($abandoned),
            ]),
        ]);
    }

    /**
     * @return mixed
     */
    private function normalizeBooleanValue(mixed $value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $value;
    }
}
