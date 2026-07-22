<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Admin;

final readonly class BulkDeleteData
{
    /**
     * @param  array<int>  $ids
     */
    public function __construct(
        public array $ids,
    ) {}

    /**
     * @param  array<int>  $ids
     */
    public static function fromIds(array $ids): self
    {
        return new self(
            ids: array_map('intval', $ids),
        );
    }
}
