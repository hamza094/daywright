<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notifications;

use App\Enums\NotificationFilter;
use Override;

class NotificationIndexRequest extends \App\Http\Requests\Api\V1\ApiQueryRequest
{
    // Static allowlist hooks removed — validation is enforced via `rules()`.
    // Reintroduce `allowedFilters()` or `defaultSorts()` only if this
    // request is later wired into Spatie QueryBuilder.

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
             * Nested notification filters.
             */
            'filter' => ['sometimes', 'array:status'],
            /**
             * Filter notifications by read state.
             *
             * @example unread
             */
            'filter.status' => ['sometimes', 'in:'.implode(',', array_column(NotificationFilter::cases(), 'value'))],
            ...$this->topLevelFilterAliasRules(['status']),
            ...$this->unsupportedQueryParameterRules(),
            /**
             * Paginator page number.
             *
             * @example 1
             */
            'page' => $this->pageRule(),
            /**
             * Number of notifications to return per page.
             *
             * @example 25
             */
            'per_page' => $this->perPageRule(),
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'filter.array' => 'Only the supported filter keys may be provided.',
            ...$this->topLevelFilterAliasMessages(['status']),
            ...$this->unsupportedQueryParameterMessages(),
        ];
    }

    public function statusFilter(): ?string
    {
        $status = $this->validated('filter.status');

        return is_string($status) ? $status : null;
    }

    public function perPage(): int
    {
        return $this->perPageValue(25);
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['filter', 'page', 'per_page'];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $filters = $this->normalizedFilters();

        $this->mergeFilters($filters);
    }
}
