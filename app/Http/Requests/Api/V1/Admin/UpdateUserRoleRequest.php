<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class UpdateUserRoleRequest extends FormRequest
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
            'is_admin' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'is_admin.required' => 'Please provide the user admin role state.',
            'is_admin.boolean' => 'The user admin role state must be true or false.',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_admin' => $this->normalizeBooleanValue($this->input('is_admin')),
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
