<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class ProjectInvitationIndexRequest extends FormRequest
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
             * Nested invitation filters.
             */
            'filter' => ['required', 'array'],
            /**
             * Invitation status filter. Only pending invitations are exposed by this endpoint.
             * The controller also accepts a top-level `status` query parameter and normalizes it here.
             *
             * @example pending
             */
            'filter.status' => ['required', 'in:pending'],
        ];
    }

    public function status(): string
    {
        return (string) $this->validated('filter.status');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'filter.required' => 'Please provide an invitation status filter.',
            'filter.status.required' => 'Please provide an invitation status filter.',
            'filter.status.in' => 'The invitation status filter must be pending.',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $inputFilters = $this->input('filter', []);
        $filters = is_array($inputFilters) ? $inputFilters : [];

        if (! array_key_exists('status', $filters) && $this->has('status')) {
            $filters['status'] = $this->input('status');
        }

        $this->merge([
            'filter' => $filters,
        ]);
    }
}
