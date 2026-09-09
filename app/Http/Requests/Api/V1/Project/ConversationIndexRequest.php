<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class ConversationIndexRequest extends ApiQueryRequest
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
             * Cursor for pagination.
             *
             * @example eyJpdiI6IjEiLCJpZF9jb2x1bW4iOiJpZCJ9
             */
            'cursor' => $this->cursorRule(),
            /**
             * Number of conversations to return per page. Default is 10.
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
        return ['cursor', 'per_page'];
    }
}
