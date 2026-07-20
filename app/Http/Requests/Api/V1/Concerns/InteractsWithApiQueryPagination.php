<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

trait InteractsWithApiQueryPagination
{
    public function pageNumber(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function cursorValue(): ?string
    {
        return $this->validated('cursor', null);
    }

    /**
     * @return array<int, string>
     */
    protected function pageRule(string $presence = 'sometimes'): array
    {
        return [$presence, 'integer', 'min:1'];
    }

    /**
     * @return array<int, string>
     */
    protected function cursorRule(string $presence = 'sometimes'): array
    {
        return [$presence, 'string'];
    }

    /**
     * @return array<int, string>
     */
    protected function perPageRule(string $presence = 'sometimes', int $min = 1, int $max = 100): array
    {
        return [$presence, 'integer', "min:{$min}", "max:{$max}"];
    }

    protected function perPageValue(int $default): int
    {
        return (int) $this->validated('per_page', $default);
    }

    protected function sortValue(string $default): string
    {
        return (string) $this->validated('sort', $default);
    }
}
