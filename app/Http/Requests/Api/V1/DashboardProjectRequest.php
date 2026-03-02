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
            /**
             * @example frontend
             */
            'search' => 'nullable|string|max:25',
            /**
             * @example latest
             */
            'sort' => 'nullable|string|in:latest,oldest,name',
            /**
             * @example true
             */
            'member' => 'nullable|boolean',
            /**
             * @example false
             */
            'abandoned' => 'nullable|boolean',
            /**
             * @example 1
             */
            'page' => 'nullable|integer|min:1',
        ];
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
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'member' => $this->normalizeBooleanValue($this->input('member')),
            'abandoned' => $this->normalizeBooleanValue($this->input('abandoned')),
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
