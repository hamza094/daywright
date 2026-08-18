<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class UserInvitationsIndexRequest extends ApiQueryRequest
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
            /**
             * Page number for pagination.
             *
             * @example 1
             */
            'page' => $this->pageRule(),
            /**
             * Number of invitations to return per page. Default is 10.
             *
             * @example 10
             */
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
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['page', 'per_page'];
    }
}
