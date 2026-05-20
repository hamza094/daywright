<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Override;

class InvitationUserSearchRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
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
            'search' => ['required', 'string', 'min:1'],
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
            'search.min' => 'The search term must be at least 1 character.',
        ];
    }

    public function searchTerm(): string
    {
        return (string) $this->validated('search');
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['search'];
    }
}
