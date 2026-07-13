<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class TaskMemberSearchRequest extends ApiQueryRequest
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
             * Search term matched against active project members.
             *
             * @example berry
             */
            'search' => ['bail', 'required', 'string', 'min:2', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'search.required' => 'Please provide a search term.',
            'search.string' => 'The search term must be a string.',
            'search.min' => 'The search term must be at least 2 characters.',
            'search.max' => 'The search term may not exceed 100 characters.',
        ];
    }

    public function searchTerm(): string
    {
        return (string) $this->validated('search');
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        if (! is_string($search)) {
            return;
        }

        $normalizedSearch = preg_replace('/\s+/u', ' ', trim($search));

        $this->merge([
            'search' => $normalizedSearch ?? trim($search),
        ]);
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['search'];
    }
}
