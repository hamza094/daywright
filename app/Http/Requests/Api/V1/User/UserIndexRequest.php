<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\ApiQueryRequest;

class UserIndexRequest extends ApiQueryRequest
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
            ...$this->unsupportedQueryParameterRules(),
            'page' => $this->pageRule(),
            'per_page' => $this->perPageRule(),
        ];
    }

    public function perPage(): int
    {
        return $this->perPageValue(10);
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['page', 'per_page'];
    }
}
