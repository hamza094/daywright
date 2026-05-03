<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class ProjectActivityIndexRequest extends FormRequest
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
            'filter.type' => ['sometimes', 'in:specifics,tasks,members,mine'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filterType(): ?string
    {
        $type = $this->validated('filter.type');

        return is_string($type) ? $type : null;
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

        if (! array_key_exists('type', $filters)) {
            foreach (['specifics', 'tasks', 'members', 'mine'] as $legacyKey) {
                if ($this->has($legacyKey)) {
                    $filters['type'] = $legacyKey;
                    break;
                }
            }
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
