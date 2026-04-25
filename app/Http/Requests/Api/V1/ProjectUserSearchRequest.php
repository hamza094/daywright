<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Override;

final class ProjectUserSearchRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['bail', 'required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'query.min' => 'Please enter at least 2 characters to search users.',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $query = $this->input('query');

        if (! is_string($query)) {
            return;
        }

        $normalizedQuery = preg_replace('/\s+/u', ' ', trim($query));

        $this->merge([
            'query' => $normalizedQuery ?? trim($query),
        ]);
    }
}
