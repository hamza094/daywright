<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationFilter;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class NotificationIndexRequest extends FormRequest
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
            'filter.status' => ['sometimes', 'in:'.implode(',', array_column(NotificationFilter::cases(), 'value'))],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function statusFilter(): ?string
    {
        $status = $this->validated('filter.status');

        return is_string($status) ? $status : null;
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 25);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (is_string($inputFilters) && $inputFilters !== '') {
            $filters['status'] = $inputFilters;
        }

        if (! array_key_exists('status', $filters) && $this->has('status')) {
            $filters['status'] = $this->input('status');
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
