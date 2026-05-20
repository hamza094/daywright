<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class MeetingIndexRequest extends ApiQueryRequest
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
            'request' => ['sometimes', 'in:previous'],
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
            'per_page' => $this->perPageRule(),
        ];
    }

    #[Override]
    public function messages(): array
    {
        return $this->unsupportedQueryParameterMessages();
    }

    public function isPrevious(): bool
    {
        return $this->validated('request') === 'previous' || $this->query('request') === 'previous';
    }

    public function perPage(): int
    {
        return $this->perPageValue((int) config('app.activity.items_limit', 10));
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['request', 'page', 'per_page'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        // nothing to normalize for now
    }
}
