<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Project\AdminProjectFilters;
use App\Enums\ProjectHealthStatus;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class ProjectFilterRequest extends FormRequest
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
            'filter.state' => ['sometimes', 'in:active,trashed'],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'filter.members' => ['sometimes', 'nullable', 'boolean'],
            'filter.status' => ['sometimes', 'required', 'in:'.implode(',', array_column(ProjectHealthStatus::cases(), 'value'))],
            'filter.tasks' => ['sometimes', 'nullable', 'boolean'],
            'filter.stage' => ['sometimes', 'required', 'integer', 'min:0', 'max:6'],
            'filter.from' => ['sometimes', 'required', 'date', 'required_with:filter.to'],
            'filter.to' => ['sometimes', 'required', 'date', 'required_with:filter.from'],
            'sort' => ['sometimes', 'required', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): AdminProjectFilters
    {
        $validatedFilters = $this->validated('filter', []);

        return AdminProjectFilters::fromArray([
            ...$validatedFilters,
            'sort' => $this->validated('sort'),
        ]);
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 10);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (is_string($inputFilters) && $inputFilters !== '') {
            $filters['state'] = $inputFilters;
        }

        foreach (['search', 'state', 'status', 'stage', 'from', 'to'] as $legacyKey) {
            if (! array_key_exists($legacyKey, $filters) && $this->has($legacyKey)) {
                $filters[$legacyKey] = $this->input($legacyKey);
            }
        }

        $members = $filters['members'] ?? $this->input('members');
        $tasks = $filters['tasks'] ?? $this->input('tasks');

        if (isset($filters['status']) && is_string($filters['status'])) {
            $this->merge([
                'filter' => array_merge($filters, [
                    'status' => mb_strtolower($filters['status']),
                    'members' => $this->normalizeBooleanValue($members),
                    'tasks' => $this->normalizeBooleanValue($tasks),
                ]),
            ]);

            return;
        }

        $this->merge([
            'filter' => array_merge($filters, [
                'members' => $this->normalizeBooleanValue($members),
                'tasks' => $this->normalizeBooleanValue($tasks),
            ]),
        ]);
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
